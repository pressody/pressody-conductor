<?php
/**
 * Wrapper to run Composer commands.
 *
 * Contains code/logic borrowed from WP-CLI Package command: https://github.com/wp-cli/package-command/blob/master/src/Package_Command.php
 *
 * @since   0.8.0
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

namespace Pressody\Conductor\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\BaseIO;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use const Pressody\Conductor\VENDOR_DIR;

/**
 * Class for wrapping Composer to be able to run composer commands.
 *
 * @since 0.8.0
 */
class ComposerWrapper implements ComposerWrapperInterface {

	const SSL_CERTIFICATE = '/rmccue/requests/library/Requests/Transport/cacert.pem';

	/**
	 * The Composer instance.
	 *
	 * @since 0.8.0
	 *
	 * @var Composer|null
	 */
	protected ?Composer $composer = null;

	/**
	 * The Composer config used to instantiate.
	 *
	 * We will use this to determine if we need to reinstantiate.
	 *
	 * @since 0.8.0
	 *
	 * @var array|string|null
	 */
	protected $composer_config = [];

	/**
	 * @since 0.8.0
	 *
	 * @var BaseIO|null
	 */
	protected ?BaseIO $io = null;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param BaseIO|null $io The IO to use for logging.
	 */
	public function __construct(
		BaseIO $io = null
	) {
		$this->io = $io;
	}

	/**
	 * Runs composer install.
	 *
	 * @see Config::defaultConfig for all available Composer config entries.
	 *
	 *
	 * @param array  $args               Various args to change Composer's behavior.
	 *                                   Provide `home_dir` to change the default Composer home directory absolute path.
	 *                                   Provide the `config` entry to overwrite any configuration Composer determines by itself.
	 *                                   Provide entry `revert_file_path` as an absolute path to a composer.json backup to revert to in case of errors.
	 *                                   Provide entry `verbose` to make the installer more verbose.
	 *
	 * @param string $composer_json_path The absolute path to the composer.json to use.
	 *
	 * @return bool
	 */
	public function install( string $composer_json_path, array $args = [] ): bool {
		$args = apply_filters( 'pressody_conductor/composer_wrapper_install_args', $args, $composer_json_path );

		// Revert on shutdown if `$args['revert_file_path']` is provided.
		if ( ! empty( $args['revert-file-path'] ) && file_exists( $args['revert-file-path'] ) ) {
			// Set to true (set to false on success to prevent revert on shutdown).
			$revert = true;
			$this->register_revert_shutdown_function( $composer_json_path, $args['revert-file-path'], $revert );
		}

		$this->set_composer_auth_env_var();
		$composer = $this->getComposer( $composer_json_path, $args );

		// Set up the EventSubscriber
		$event_subscriber = new ComposerPackageManagerEventSubscriber( $this->io );
		$composer->getEventDispatcher()->addSubscriber( $event_subscriber );
		// Set up the installer
		$install = Installer::create( $this->io, $composer );
		if ( ! empty( $args['update'] ) ) {
			// Installer class will only override composer.lock with this flag
			$install->setUpdate( true );
		}
		if ( isset( $args['dry-run'] ) ) {
			$install->setDryRun( (bool) $args['dry-run'] );
		}
		if ( isset( $args['dev-mode'] ) ) {
			$install->setDevMode( (bool) $args['dev-mode'] );
		}
		if ( ! empty( $args['preferred-install'] ) ) {
			switch ( $args['preferred-install'] ) {
				case 'dist':
					$install->setPreferDist();
					break;
				case 'source':
					$install->setPreferSource();
					break;
				default:
					break;
			}
		}
		if ( isset( $args['optimize-autoloader'] ) ) {
			$install->setOptimizeAutoloader( (bool) $args['optimize-autoloader'] );
		}
		if ( isset( $args['dump-autoloader'] ) ) {
			$install->setDumpAutoloader( (bool) $args['dump-autoloader'] );
		}
		if ( isset( $args['prefer-stable'] ) ) {
			$install->setPreferStable( $args['prefer-stable'] );
		}
		if ( isset( $args['prefer-lowest'] ) ) {
			$install->setPreferLowest( $args['prefer-lowest'] );
		}
		if ( isset( $args['ignore-platform-req'] ) ) {
			$install->setIgnorePlatformRequirements( $args['ignore-platform-req'] );
		}
		if ( ! empty( $args['verbose'] ) ) {
			$install->setVerbose( (bool) $args['verbose'] );
			$this->io->setVerbosity( $this->io::VERBOSE );
		}
		if ( ! empty( $args['debug'] ) ) {
			$install->setVerbose( true );
			$this->io->setVerbosity( $this->io::DEBUG );
		}

		if ( $args['output-progress'] ) {
			$composer->getInstallationManager()->setOutputProgress( (bool) $args['output-progress'] );
		}

		// Try running the installer, but revert composer.json if failed
		$res = false;
		try {
			$res = $install->run();
		} catch ( \Exception $e ) {
			$this->io->warning( $e->getMessage() );
		}

		if ( 0 === $res ) {
			$revert = false;
			if ( ! empty( $args['update'] ) ) {
				$this->io->info( 'Composer update was successful.' );
			} else {
				$this->io->info( 'Composer install was successful.' );
			}

			return true;
		} else {
			$res_msg = $res ? " (Composer return code {$res})" : ''; // $res may be null apparently.
			$this->io->debug( "composer.json content:\n" . file_get_contents( $composer_json_path ) );
			if ( ! empty( $args['update'] ) ) {
				$this->io->error( "Composer update failed{$res_msg}." );
			} else {
				$this->io->error( "Composer install failed{$res_msg}." );
			}
		}

		return false;
	}

	/**
	 * @since 0.8.0
	 *
	 * @param string $composer_json_path Absolute path to the project's composer.json file.
	 * @param array  $args               Various args to alter Composer's behavior.
	 *                                   Provide `home_dir` to change the default Composer home directory absolute path.
	 *                                   Provide `config` to merge with the default Composer config.
	 *
	 * @return Composer
	 */
	public function getComposer( string $composer_json_path, array $args = [] ): Composer {
		if ( null === $this->composer || $this->composer_config !== $composer_json_path ) {
			$this->avoid_composer_ca_bundle();
			try {
				$factory = new Factory();

				// Change the cwd to the directory where composer.json resides.
				chdir( pathinfo( $composer_json_path, PATHINFO_DIRNAME ) );

				// Prevent DateTime error/warning when no timezone set.
				// Note: The package is loaded before WordPress load, For environments that don't have set time in php.ini.
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set,WordPress.PHP.NoSilencedErrors.Discouraged
				date_default_timezone_set( @date_default_timezone_get() );

				$this->composer = $factory->createComposer( $this->io, $composer_json_path, false, $this->getComposerHome( $args ) );

				// Handle alterations received through $args.
				if ( ! empty( $args['config'] ) ) {
					$composerConfig = $this->composer->getConfig();
					$composerConfig->merge( $args['config'] );
					$this->composer->setConfig( $composerConfig );
				}

			} catch ( \InvalidArgumentException $e ) {
				$this->io->error( $e->getMessage() );
				exit( 1 );
			}

			$this->composer_config = $composer_json_path;
		}

		return $this->composer;
	}

	/**
	 * @since 0.8.0
	 *
	 * @return IOInterface
	 */
	public function getIO() {
		if ( null === $this->io ) {
			$this->io = new BufferIO();
		}

		return $this->io;
	}

	/**
	 * @since 0.8.0
	 *
	 * @param BaseIO $io
	 */
	public function setIO( BaseIO $io ) {
		$this->io = $io;
	}

	/**
	 * Sets `COMPOSER_AUTH` environment variable (which Composer merges into the config setup in `Composer\Factory::createConfig()`) depending on available environment variables.
	 * Avoids authorization failures when accessing various sites.
	 *
	 * @since 0.8.0
	 */
	private function set_composer_auth_env_var() {
		$changed       = false;
		$composer_auth = getenv( 'COMPOSER_AUTH' );
		if ( false !== $composer_auth ) {
			$composer_auth = json_decode( $composer_auth, true /*assoc*/ );
		}
		if ( empty( $composer_auth ) || ! is_array( $composer_auth ) ) {
			$composer_auth = [];
		}
		$github_token = getenv( 'GITHUB_TOKEN' );
		if ( ! isset( $composer_auth['github-oauth'] ) && is_string( $github_token ) ) {
			$composer_auth['github-oauth'] = [ 'github.com' => $github_token ];
			$changed                       = true;
		}
		if ( $changed ) {
			putenv( 'COMPOSER_AUTH=' . json_encode( $composer_auth ) );
		}
	}

	/**
	 * Avoid using default Composer CA bundle if in phar as we don't include it.
	 * @see   https://github.com/composer/ca-bundle/blob/1.1.0/src/CaBundle.php#L64
	 *
	 * @since 0.8.0
	 */
	private function avoid_composer_ca_bundle() {
		if ( ! getenv( 'SSL_CERT_FILE' ) && ! getenv( 'SSL_CERT_DIR' ) && ! ini_get( 'openssl.cafile' ) && ! ini_get( 'openssl.capath' ) ) {
			$certificate = \path_join( VENDOR_DIR, self::SSL_CERTIFICATE );
			putenv( "SSL_CERT_FILE={$certificate}" );
		}
	}

	/**
	 * Registers a PHP shutdown function to revert composer.json unless
	 * referenced `$revert` flag is false.
	 *
	 * @since 0.8.0
	 *
	 * @param string  $composer_json_path   Path to composer.json.
	 * @param string  $composer_backup_path Path to composer.json backup.
	 * @param bool   &$revert               Flags whether to revert or not.
	 */
	private function register_revert_shutdown_function( $composer_json_path, $composer_backup_path, &$revert ) {
		// Allocate all needed memory beforehand as much as possible.
		$revert_msg      = "Reverted composer.json.\n";
		$revert_fail_msg = "Failed to revert composer.json.\n";
		$memory_msg      = "Composer client ran out of memory.\n";
		$memory_string   = 'Allowed memory size of';
		$error_array     = [
			'type'    => 42,
			'message' => 'Some random dummy string to take up memory',
			'file'    => 'Another random string, which would be a filename this time',
			'line'    => 314,
		];
		$io              = $this->io;

		register_shutdown_function(
			static function () use (
				$composer_json_path,
				$composer_backup_path,
				&$revert,
				$io,
				$revert_msg,
				$revert_fail_msg,
				$memory_msg,
				$memory_string,
				$error_array
			) {
				if ( $revert && ! empty( $composer_backup_path ) ) {
					if ( false !== copy( $composer_backup_path, $composer_json_path ) ) {
						$io->warning( $revert_msg );
					} else {
						$io->error( $revert_fail_msg );
					}
				}

				$error_array = error_get_last();
				if ( is_array( $error_array ) && false !== strpos( $error_array['message'], $memory_string ) ) {
					$io->error( $memory_msg );
				}
			}
		);
	}

	/**
	 * @since 0.8.0
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	private function getComposerHome( array $args = [] ): string {
		if ( ! empty( $args['home-dir'] ) && is_string( $args['home-dir'] ) ) {
			$home = $args['home-dir'];
		}

		if ( ! empty( $home ) ) {
			// Make sure that the directory exists.
			wp_mkdir_p( $home );
		} else {
			$home = getenv( 'COMPOSER_HOME' );
		}

		if ( ! $home ) {
			if ( defined( 'PHP_WINDOWS_VERSION_MAJOR' ) ) {
				if ( ! getenv( 'APPDATA' ) ) {
					throw new \RuntimeException( 'The APPDATA or COMPOSER_HOME environment variable must be set for composer to run correctly' );
				}
				$home = strtr( getenv( 'APPDATA' ), '\\', '/' ) . '/Composer';
			} else {
				if ( ! getenv( 'HOME' ) ) {
					throw new \RuntimeException( 'The HOME or COMPOSER_HOME environment variable must be set for composer to run correctly' );
				}
				$home = rtrim( getenv( 'HOME' ), '/' ) . '/.composer';
			}
		}

		return $home;
	}

	/**
	 * Whether debug mode is enabled.
	 *
	 * @since 0.8.0
	 *
	 * @return bool
	 */
	protected function is_debug_mode(): bool {
		return \defined( 'WP_DEBUG' ) && true === WP_DEBUG;
	}
}
