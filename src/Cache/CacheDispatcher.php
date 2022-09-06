<?php
/**
 * Site-cache events dispatcher routines.
 *
 * @since   0.9.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\Cache;

use CacheTool\Adapter\FastCGI;
use CacheTool\CacheTool;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\Event;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Pressody\Conductor\Queue\QueueInterface;
use Pressody\Conductor\Queue\ActionQueue;
use Symfony\Component\Finder\Finder;

/**
 * Class to dispatch events related to site-cache(s).
 *
 * @since 0.9.0
 */
class CacheDispatcher {

	/**
	 * Queue.
	 *
	 * @since 0.9.0
	 *
	 * @var QueueInterface|null
	 */
	protected static ?QueueInterface $queue = null;

	/**
	 * Handle Composer events and fire actions accordingly.
	 *
	 * This is intended to be called from Composer scripts and receive Composer events instances.
	 * @see   https://getcomposer.org/doc/articles/scripts.md#event-classes
	 *
	 * @since 0.9.0
	 *
	 * @param Event|null $event
	 */
	public static function handle_event( Event $event = null ) {
		if ( ! $event ) {
			return;
		}

		// Make sure that everything we need is loaded.
		try {
			self::load_wp( self::determine_site_root_path( $event ) );
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		switch ( $event->getName() ) {
			case PackageEvents::PRE_PACKAGE_INSTALL:
				// This package didn't exist prior, so no need to do anything.
				break;
			case PackageEvents::PRE_PACKAGE_UPDATE:
			case PackageEvents::PRE_PACKAGE_UNINSTALL:
				// Before changing the package files, invalidate the opcache for the current files,
				// so we don't lose files that might be deleted.
				self::invalidate_package_opcache( $event );
				break;
			case PackageEvents::POST_PACKAGE_INSTALL:
				// This is a new package that should be compiled.
				self::compile_package_opcache( $event );
				self::schedule_cache_clear( $event );
				break;
			case PackageEvents::POST_PACKAGE_UPDATE:
			case PackageEvents::POST_PACKAGE_UNINSTALL:
				// No need to compile changes since we've invalidated before package change.
				self::schedule_cache_clear( $event );
				break;
		}

	}

	/**
	 * Compile the opcache after a package event (install, update, uninstall).
	 *
	 * This is intended to be called from Composer scripts and receive Composer events instances.
	 * @see   https://getcomposer.org/doc/articles/scripts.md#event-classes
	 *
	 * @since 0.9.0
	 *
	 * @param Event|null $event
	 */
	public static function compile_package_opcache( Event $event = null ) {
		if ( ! $event instanceof PackageEvent || ! method_exists( $event->getOperation(), 'getPackage' ) ) {
			return;
		}

		// Make sure that everything we need is loaded.
		try {
			self::load_wp( self::determine_site_root_path( $event ) );
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		$package             = $event->getOperation()->getPackage();
		$installationManager = $event->getComposer()->getInstallationManager();
		$packagePath         = $installationManager->getInstallPath( $package );
		if ( empty( $packagePath ) ) {
			return;
		}

		try {
			$cachetool = self::get_cachetool();
			if ( ! $cachetool->extension_loaded( 'Zend OPcache' ) ) {
				return;
			}
			$splFiles = self::prepareFileList( $packagePath );
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		foreach ( $splFiles as $file ) {
			$cachetool->opcache_compile_file( $file->getRealPath() );
		}
	}

	/**
	 * Invalidate the opcache after a package event (install, update, uninstall).
	 *
	 * This is intended to be called from Composer scripts and receive Composer events instances.
	 * @see   https://getcomposer.org/doc/articles/scripts.md#event-classes
	 *
	 * @since 0.9.0
	 *
	 * @param Event|null $event
	 */
	public static function invalidate_package_opcache( Event $event = null ) {
		if ( ! $event instanceof PackageEvent || ! method_exists( $event->getOperation(), 'getPackage' ) ) {
			return;
		}

		// Make sure that everything we need is loaded.
		try {
			self::load_wp( self::determine_site_root_path( $event ) );
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		$package             = $event->getOperation()->getPackage();
		$installationManager = $event->getComposer()->getInstallationManager();
		$packagePath         = $installationManager->getInstallPath( $package );
		if ( empty( $packagePath ) ) {
			return;
		}

		try {
			$cachetool = self::get_cachetool();
			if ( ! $cachetool->extension_loaded( 'Zend OPcache' ) ) {
				return;
			}
			$info = $cachetool->opcache_get_status( true );
			if ( empty( $info['scripts'] ) ) {
				// Opcache is either disabled or there are no scripts cached.
				return;
			}
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		foreach ( $info['scripts'] as $script ) {
			if ( empty( $script['full_path'] ) ) {
				continue;
			}

			if ( 0 === strpos( $script['full_path'], $packagePath ) ) {
				$cachetool->opcache_invalidate( $script['full_path'] );
			}
		}
	}

	protected static function get_cachetool() {
		$adapter = null;
		if ( defined( 'PD_PHP_FCGI_HOST' ) && ! empty( PD_PHP_FCGI_HOST ) ) {
			$adapter = new FastCGI( PD_PHP_FCGI_HOST );
		}

		return CacheTool::factory( $adapter );
	}

	/**
	 * Schedule the async event to refresh the site cache.
	 *
	 * By scheduling an async event, slightly in the future, we avoid race conditions
	 * with multiple instructions to clear the cache at the same time.
	 *
	 * This is intended to be called from Composer scripts and receive Composer events instances.
	 * @see   https://getcomposer.org/doc/articles/scripts.md#event-classes
	 *
	 * @since 0.9.0
	 *
	 * @param Event|null $event
	 */
	public static function schedule_cache_clear( Event $event = null ) {
		// Make sure that everything we need is loaded.
		try {
			self::load_wp( self::determine_site_root_path( $event ) );
		} catch ( \Exception $e ) {
			// Bail.
			return;
		}

		if ( ! self::get_queue()->get_next( 'pressody_conductor/clear_site_cache' ) ) {
			$context = [];
			if ( $event ) {
				$context['event'] = [
					'name' => $event->getName(),
				];
				if ( $event instanceof PackageEvent ) {
					$context['event']['operation_type'] = $event->getOperation()->getOperationType();
					if ( method_exists( $event->getOperation(), 'getPackage' ) ) {
						$context['event']['package_name'] = $event->getOperation()->getPackage()->getName();
						$context['event']['package_type'] = $event->getOperation()->getPackage()->getType();

						// We only schedule for certain package types, not all.
						if ( ! in_array( $context['event']['package_type'], [
							'wordpress-plugin',
							'wordpress-theme',
							'wordpress-core',
						] ) ) {
							return;
						}
					}
				}
			}
			// We schedule 30 seconds into the future.
			self::get_queue()->schedule_single( time() + 30, 'pressody_conductor/clear_site_cache', $context, 'plt_con' );
		}
	}

	protected static function load_wp( string $root_path = '' ) {
		if ( ! empty( $root_path ) && file_exists( $root_path . '/web/wp-config.php' ) ) {
			require_once $root_path . '/web/wp-config.php';
		} else if ( file_exists( getcwd() . '/web/wp-config.php' ) ) {
			require_once( getcwd() . '/web/wp-config.php' );
		}
	}

	protected static function determine_site_root_path( Event $event = null ) {
		$root_path = '';
		if ( $event && method_exists( $event, 'getComposer' ) ) {
			$root_path = dirname( $event->getComposer()->getConfig()->get( 'vendor-dir' ) );
		}

		if ( empty( $root_path ) ) {
			$root_path = getcwd();
		}

		return $root_path;
	}

	/**
	 * Get a list of files searched recursively in the path, possibly excluding some of them.
	 *
	 * @param string $path    Absolute path to the directory to search in.
	 * @param array  $exclude Patterns to exclude by. See https://symfony.com/doc/current/components/finder.html (the notPath part).
	 *
	 * @return \Traversable|\SplFileInfo[]
	 */
	protected static function prepareFileList( $path, $exclude = [] ) {
		return Finder::create()
		             ->files()
		             ->in( $path )
		             ->name( '*.php' )
		             ->notPath( '/Tests/' )
		             ->notPath( '/tests/' )
		             ->notPath( $exclude )
		             ->ignoreUnreadableDirs()
		             ->ignoreDotFiles( true )
		             ->ignoreVCS( true );
	}

	protected static function get_queue() {
		if ( ! self::$queue ) {
			self::$queue = new ActionQueue();
		}

		return self::$queue;
	}

	protected static function set_queue( QueueInterface $queue ) {
		self::$queue = $queue;
	}
}
