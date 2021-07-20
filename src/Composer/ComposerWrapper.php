<?php
/**
 * Wrapper to run Composer commands.
 *
 * Contains code/logic borrowed from WP-CLI Package command: https://github.com/wp-cli/package-command/blob/master/src/Package_Command.php
 *
 * @since   0.8.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Config\JsonConfigSource;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\BaseIO;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;
use PixelgradeLT\Conductor\Utils\ArrayHelpers;
use Seld\JsonLint\DuplicateKeyException;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use const PixelgradeLT\Conductor\VENDOR_DIR;

/**
 * Class for wrapping Composer to be able to run composer commands.
 *
 * @since 0.8.0
 */
class ComposerWrapper implements Wrapper {

	const SSL_CERTIFICATE = '/rmccue/requests/library/Requests/Transport/cacert.pem';

	/**
	 * The Composer instance.
	 *
	 * @since 0.8.0
	 *
	 * @var Composer
	 */
	protected $composer = null;

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
	 * Absolute path to the home directory to use for Composer.
	 *
	 * This is directory will be used internally by Composer for caching and stuff.
	 *
	 * @since 0.8.0
	 *
	 * @var string|null
	 */
	protected ?string $composer_home_dir = null;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param string|null $composer_home_dir Absolute path to set the Composer's home directory to.
	 *                                       This is a directory for Composer's internal use, not the directory with the project.
	 * @param BaseIO|null $io The IO to use for logging.
	 */
	public function __construct(
		string $composer_home_dir = null,
		BaseIO $io = null
	) {
		$this->composer_home_dir = $composer_home_dir;
		$this->io                = $io;
	}

	/**
	 * Runs composer install.
	 *
	 * @param string $composer_json_path
	 * @param string $composer_backup_path
	 * @param bool   $insecure
	 *
	 * @throws \Exception
	 */
	public function install( string $composer_json_path, string $composer_backup_path = '', bool $insecure = false ): bool {
		// load auth.json authentication information and pass it to the io interface
		$io = $this->getIO();
		$io->loadConfiguration( $this->getConfiguration() );

		try {
			$config = $this->getDynamicConfig( $composer_json_path );
		} catch ( JsonValidationException $e ) {
			$this->io->error( 'Could not validate the composer.json file: ' . $e->getMessage() );
			return false;
		} catch ( ParsingException $e ) {
			$this->io->error( 'Could not parse the composer.json file: ' . $e->getMessage() );
			return false;
		}

		$this->set_composer_auth_env_var();

		// Revert on shutdown if `$revert` is true (set to false on success).
		$revert = true;
		$this->register_revert_shutdown_function( $composer_json_path, $composer_backup_path, $revert );

		$composer = $this->getComposer( $config, pathinfo( $composer_json_path, PATHINFO_DIRNAME ) );

		// Set up the EventSubscriber
		$event_subscriber = new ComposerPackageManagerEventSubscriber( $this->io );
		$composer->getEventDispatcher()->addSubscriber( $event_subscriber );
		// Set up the installer
		$install = Installer::create( $this->io, $composer );
		$install->setUpdate( true );       // Installer class will only override composer.lock with this flag
		$install->setPreferSource( true ); // Use VCS when VCS for easier contributions.

		// Try running the installer, but revert composer.json if failed
		$res = false;
		try {
			$res = $install->run();
		} catch ( \Exception $e ) {
			$this->io->warning( $e->getMessage() );
		}

		// TODO: The --insecure flag should cause another Composer run with verify disabled.

		if ( 0 === $res ) {
			$revert = false;
			$this->io->info( 'Composer install was successful.' );
			return true;
		} else {
			$res_msg = $res ? " (Composer return code {$res})" : ''; // $res may be null apparently.
			$this->io->debug( "composer.json content:\n" . file_get_contents( $composer_json_path ), 'packages' );
			$this->io->error( "Composer install failed{$res_msg}." );
		}

		return false;
	}

	//	/**
	//	 * @param array $args
	//	 *
	//	 * @throws \Exception
	//	 * @return PackageInterface[]
	//	 */
	//	public function getPackages( array $args ): array {
	//		// load auth.json authentication information and pass it to the io interface
	//		$io = $this->getIO();
	//		$io->loadConfiguration( $this->getConfiguration() );
	//
	//		$verbose = false;
	//
	//		$config = $this->getDynamicConfig( $args );
	//
	//		$packagesFilter = ! empty( $args['packages'] ) ? $args['packages'] : [];
	//		$repositoryUrl  = ! empty( $args['repository-url'] ) ? $args['repository-url'] : null;
	//		$skipErrors     = ! empty( $args['skip-errors'] ) ? $args['skip-errors'] : false;
	//		$outputDir      = ! empty( $args['output-dir'] ) ? $args['output-dir'] : get_temp_dir();
	//
	//		if ( null !== $repositoryUrl && count( $packagesFilter ) > 0 ) {
	//			throw new \InvalidArgumentException( 'The arguments "package" and "repository-url" can not be used together.' );
	//		}
	//
	//		$composer         = $this->getComposer( $config );
	//		$packageSelection = new ComposerPackageSelection( $io, $outputDir, $config, $skipErrors );
	//
	//		if ( null !== $repositoryUrl ) {
	//			$packageSelection->setRepositoryFilter( $repositoryUrl, false );
	//		} else {
	//			$packageSelection->setPackagesFilter( $packagesFilter );
	//		}
	//
	//		$packages = $packageSelection->select( $composer, $verbose );
	//
	//		if ( isset( $config['archive']['directory'] ) ) {
	//			$downloads = new ComposerArchiveBuilder( $io, $outputDir, $config, $skipErrors );
	//			$downloads->setComposer( $composer );
	//			$downloads->dump( $packages );
	//		}
	//
	//		$packages = $packageSelection->clean();
	//
	//		return $packages;
	//	}

	/**
	 * Determine the dynamic configuration depending on the received args.
	 *
	 * This is not the same as a local (file-based) Composer config. That is taken into account,
	 * but this one will overwrite that one.
	 *
	 * @since 0.8.0
	 *
	 * @param array|string $args Full or partial Composer.json contents or an absolute path to a config file (e.g. to composer.json).
	 *
	 * @throws \Composer\Json\JsonValidationException
	 * @throws \Seld\JsonLint\ParsingException
	 * @return array
	 */
	protected function getDynamicConfig( $args = [] ): array {
		// Start with the default config.
		$config = $this->getDefaultDynamicConfig();

		if ( is_string( $args ) ) {
			$composerFile = $args;

			$file = new JsonFile( $composerFile, null, $this->io );
			if ( ! $file->exists() ) {
				if ( $composerFile === './composer.json' || $composerFile === 'composer.json' ) {
					$message = 'Composer could not find a composer.json file in ' . getcwd();
				} else {
					$message = 'Composer could not find the config file: ' . $composerFile;
				}

				throw new \InvalidArgumentException( $message );
			}

			$file->validateSchema( JsonFile::LAX_SCHEMA );
			$jsonParser = new JsonParser;
			try {
				$jsonParser->parse( file_get_contents( $composerFile ), JsonParser::DETECT_KEY_CONFLICTS );
			} catch ( DuplicateKeyException $e ) {
				$details = $e->getDetails();
				$this->io->writeError( '<warning>Key ' . $details['key'] . ' is a duplicate in ' . $composerFile . ' at line ' . $details['line'] . '</warning>' );
			}

			$args = $file->read();
		}

		// Depending on the received args, make the config modifications.
		$config = $this->parseDynamicConfigArgs( $config, $args );

		// Allow others to filter this and add or modify the Composer client config (like adding OAuth tokens).
		return apply_filters( 'pixelgradelt_conductor/composer_wrapper_config', $config, $args );
	}

	/**
	 * Given a config and a set of arguments, make the necessary config modifications.
	 *
	 * @since 0.8.0
	 *
	 * @param array $config The initial config.
	 * @param array $args Partial or full composer.json config.
	 *
	 * @return array The modified config.
	 */
	protected function parseDynamicConfigArgs( array $config, array $args ): array {
		$originalConfig = $config;

		// Just merge the two configs.
		$config = ArrayHelpers::array_merge_recursive_distinct( $config, $args );

		return apply_filters( 'pixelgradelt_conductor/composer_wrapper_config_parse_args', $config, $args, $originalConfig );
	}

	/**
	 * @since 0.8.0
	 *
	 * @return array
	 */
	public function getDefaultDynamicConfig(): array {
		$default_config = [
			'repositories'      => [],
			'minimum-stability' => 'dev',

			'prefer-stable' => true,
			'prefer-lowest' => false,
			// This is the default Composer config to pass when initializing Composer.
			'config'        => [],
		];

		// If we are in a local/development environment, relax further.
		if ( $this->is_debug_mode() ) {
			// Skip SSL verification since we may be using self-signed certificates.
			$default_config['config']['disable-tls'] = true;
			$default_config['config']['secure-http'] = false;
		}

		return apply_filters( 'pixelgradelt_conductor/composer_wrapper_default_config', $default_config );
	}

	/**
	 * @since 0.8.0
	 *
	 * @param array|string|null $config Either a configuration array or a filename to read from, if null it will read from the default filename
	 * @param string|null       $cwd    Absolute path to where the current working directory should be changed to.
	 *
	 * @return Composer
	 */
	public function getComposer( $config = null, $cwd = null ): Composer {
		if ( null === $this->composer || $this->composer_config !== $config ) {
			try {
				$factory = new Factory();
				// We will set the Composer current working directory to our home directory, if provided.
				if ( ! empty( $this->composer_home_dir ) ) {
					// Make sure that the directory exists.
					wp_mkdir_p( $this->composer_home_dir );
				}

				if ( is_string( $cwd ) ) {
					// Composer's auto-load generating code makes some assumptions about where
					// the 'vendor-dir' is, and where Composer is running from.
					// Best to just enforce it ourselves by changing the current working dir.
					chdir( $cwd );
				}

				// Prevent DateTime error/warning when no timezone set.
				// Note: The package is loaded before WordPress load, For environments that don't have set time in php.ini.
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set,WordPress.PHP.NoSilencedErrors.Discouraged
				date_default_timezone_set( @date_default_timezone_get() );

				$this->composer = $factory->createComposer( $this->io, $config, false, $this->getComposerHome() );
			} catch ( \InvalidArgumentException $e ) {
				$this->io->error( $e->getMessage() );
				exit( 1 );
			}

			$this->composer_config = $config;
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

		register_shutdown_function(
			static function () use (
				$composer_json_path,
				$composer_backup_path,
				&$revert,
				$revert_msg,
				$revert_fail_msg,
				$memory_msg,
				$memory_string,
				$error_array
			) {
				if ( $revert && ! empty( $composer_backup_path ) ) {
					if ( false !== copy( $composer_backup_path, $composer_json_path ) ) {
						fwrite( STDERR, $revert_msg );
					} else {
						fwrite( STDERR, $revert_fail_msg );
					}
				}

				$error_array = error_get_last();
				if ( is_array( $error_array ) && false !== strpos( $error_array['message'], $memory_string ) ) {
					fwrite( STDERR, $memory_msg );
				}
			}
		);
	}

	/**
	 * Check whether we are dealing with Composer version 2.0.0+.
	 *
	 * @since 0.8.0
	 *
	 * @return bool
	 */
	private function is_composer_v2() {
		return version_compare( Composer::getVersion(), '2.0.0', '>=' );
	}

	/**
	 * @since 0.8.0
	 *
	 * @throws \Seld\JsonLint\ParsingException
	 * @return Config
	 */
	private function getConfiguration(): Config {
		$config = new Config();

		// add dir to the config
		$config->merge( [ 'config' => [ 'home' => $this->getComposerHome() ] ] );

		// load global auth file
		$file = new JsonFile( $config->get( 'home' ) . '/auth.json' );
		if ( $file->exists() ) {
			$config->merge( [ 'config' => $file->read() ] );
		}
		$config->setAuthConfigSource( new JsonConfigSource( $file, true ) );

		return $config;
	}

	/**
	 * @since 0.8.0
	 *
	 * @return string
	 */
	private function getComposerHome(): string {
		$home = $this->composer_home_dir;
		if ( ! $home ) {
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
