<?php
/**
 * Site-cache events dispatcher routines.
 *
 * @since   0.9.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Cache;

use Composer\EventDispatcher\Event;
use Composer\Installer\PackageEvent;
use PixelgradeLT\Conductor\Queue\QueueInterface;
use PixelgradeLT\Conductor\Queue\ActionQueue;

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
	 * Schedule the async event to refresh the site cache.
	 *
	 * By scheduling an async event, slightly in the future, we avoid race conditions
	 * with multiple instructions to clear the cache at the same time.
	 *
	 * This is intended to be called from Composer scripts and receive Composer events instances.
	 * @see https://getcomposer.org/doc/articles/scripts.md#event-classes
	 *
	 * @since 0.9.0
	 *
	 * @param Event|null $event
	 */
	public static function schedule_cache_clear( Event $event = null ) {
		self::load_wp( self::determine_site_root_path( $event ) );

		if ( ! self::get_queue()->get_next( 'pixelgradelt_conductor/clear_site_cache' ) ) {
			$context = [];
			if ( $event ) {
				$context['event'] = [
					'name' => $event->getName(),
				];
				if ( $event instanceof PackageEvent ) {
					$context['event']['operation_type'] = $event->getOperation()->getOperationType();
					$context['event']['package_name'] = $event->getOperation()->getPackage()->getName();
					$context['event']['package_type'] = $event->getOperation()->getPackage()->getType();

					// We only schedule for certain package types, not all.
					if ( ! in_array( $context['event']['package_type'], [ 'wordpress-plugin', 'wordpress-theme', 'wordpress-core', ] ) ) {
						return;
					}
				}
			}
			// We schedule 30 seconds into the future.
			self::get_queue()->schedule_single( time() + 30, 'pixelgradelt_conductor/clear_site_cache', $context, 'pixelgrade-conductor' );
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
			$root_path = dirname( $event->getComposer()->getConfig()->get('vendor-dir' ) );
		}

		if ( empty( $root_path ) ) {
			$root_path = getcwd();
		}

		return $root_path;
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
