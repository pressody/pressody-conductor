<?php
/**
 * Client to communicate with an external Composer repository.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Client;

use Composer\Composer;
use Composer\Config;
use Composer\Config\JsonConfigSource;
use Composer\Factory;
use Composer\IO\BaseIO;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\PackageInterface;
use Composer\Semver\VersionParser;
use PixelgradeLT\Conductor\Client\Builder\ComposerArchiveBuilder;

/**
 * Class for communicating with an external Composer repository.
 *
 * @since 0.1.0
 */
class ComposerClient implements Client {

	/**
	 * The Composer instance.
	 *
	 * @var Composer
	 */
	protected $composer = null;

	/**
	 * The Composer config used to instantiate.
	 *
	 * We will use this to determine if we need to reinstantiate.
	 *
	 * @var array|string|null
	 */
	protected $composer_config = [];

	/**
	 *
	 * @var BaseIO
	 */
	protected $io = null;

	/**
	 * Absolute path to the home directory to use for Composer.
	 *
	 * This is directory will be used internally by Composer for caching and stuff.
	 * @var string
	 */
	protected $composer_home_dir = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param BaseIO|null $io
	 */
	public function __construct(
		string $composer_home_dir = null,
		BaseIO $io = null
	) {
		$this->composer_home_dir = $composer_home_dir;
		$this->io                = $io;
	}

	/**
	 * @param array $args
	 *
	 * @throws \Exception
	 * @return PackageInterface[]
	 */
	public function getPackages( array $args ): array {
		// load auth.json authentication information and pass it to the io interface
		$io = $this->getIO();
		$io->loadConfiguration( $this->getConfiguration() );

		$verbose = false;

		$config = $this->getDynamicConfig( $args );

		$packagesFilter = ! empty( $args['packages'] ) ? $args['packages'] : [];
		$repositoryUrl  = ! empty( $args['repository-url'] ) ? $args['repository-url'] : null;
		$skipErrors     = ! empty( $args['skip-errors'] ) ? $args['skip-errors'] : false;
		$outputDir      = ! empty( $args['output-dir'] ) ? $args['output-dir'] : get_temp_dir();

		if ( null !== $repositoryUrl && count( $packagesFilter ) > 0 ) {
			throw new \InvalidArgumentException( 'The arguments "package" and "repository-url" can not be used together.' );
		}

		$composer         = $this->getComposer( $config );
		$packageSelection = new ComposerPackageSelection( $io, $outputDir, $config, $skipErrors );

		if ( null !== $repositoryUrl ) {
			$packageSelection->setRepositoryFilter( $repositoryUrl, false );
		} else {
			$packageSelection->setPackagesFilter( $packagesFilter );
		}

		$packages = $packageSelection->select( $composer, $verbose );

		if ( isset( $config['archive']['directory'] ) ) {
			$downloads = new ComposerArchiveBuilder( $io, $outputDir, $config, $skipErrors );
			$downloads->setComposer( $composer );
			$downloads->dump( $packages );
		}

		$packages = $packageSelection->clean();

		return $packages;
	}

	/**
	 * @param PackageInterface[] $packages         List of packages to standardize.
	 * @param bool               $minimumStability The minimum stability to filter version packages by.
	 *
	 * @return array List of packages with a hierarchical organization: package > versions > version package config.
	 */
	public function standardizePackagesForJson( array $packages, $minimumStability = false ): array {
		$packagesByName = [];
		$dumper         = new ArrayDumper();
		foreach ( $packages as $package ) {
			$packagesByName[ $package->getName() ][ $package->getPrettyVersion() ] = $dumper->dump( $package );
		}

		// Prune version packages by the specified minimum stability.
		if ( false !== $minimumStability ) {
			$minimumStability      = VersionParser::normalizeStability( $minimumStability );
			$acceptableStabilities = [];
			foreach ( BasePackage::$stabilities as $stability => $value ) {
				if ( $value <= BasePackage::$stabilities[ $minimumStability ] ) {
					$acceptableStabilities[ $stability ] = $value;
				}
			}

			foreach ( $packagesByName as $packageName => $versionPackages ) {
				foreach ( $versionPackages as $version => $versionConfig ) {
					$versionStability = VersionParser::parseStability( $versionConfig['version'] );
					if ( ! isset( BasePackage::$stabilities[ $versionStability ] ) || ! in_array( BasePackage::$stabilities[ $versionStability ], $acceptableStabilities ) ) {
						unset( $packagesByName[ $packageName ][ $version ] );
					}
				}
			}
		}

		return $packagesByName;
	}

	/**
	 * @param PackageInterface[] $packages
	 * @param array              $args Composer config args.
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function archivePackages( array $packages, array $args ): void {
		// load auth.json authentication information and pass it to the io interface
		$io = $this->getIO();
		$io->loadConfiguration( $this->getConfiguration() );

		$config = $this->getDynamicConfig( $args );

		$packagesFilter = ! empty( $args['packages'] ) ? $args['packages'] : [];
		$repositoryUrl  = ! empty( $args['repository-url'] ) ? $args['repository-url'] : null;
		$skipErrors     = ! empty( $args['skip-errors'] ) ? $args['skip-errors'] : false;
		$outputDir      = ! empty( $args['output-dir'] ) ? $args['output-dir'] : get_temp_dir();

		if ( null !== $repositoryUrl && count( $packagesFilter ) > 0 ) {
			throw new \InvalidArgumentException( 'The arguments "package" and "repository-url" can not be used together.' );
		}

		$composer = $this->getComposer( $config );

		if ( isset( $config['archive']['directory'] ) ) {
			$downloads = new ComposerArchiveBuilder( $io, $outputDir, $config, $skipErrors );
			$downloads->setComposer( $composer );
			$downloads->dump( $packages );
		}
	}

	/**
	 * @param PackageInterface $package
	 * @param array            $args Composer config args.
	 *
	 * @throws \Exception
	 * @return string The patch to the package archive.
	 */
	public function archivePackage( PackageInterface $package, array $args = [] ): string {
		// load auth.json authentication information and pass it to the io interface
		$io = $this->getIO();
		$io->loadConfiguration( $this->getConfiguration() );

		$config = $this->getDynamicConfig( $args );

		if ( ! isset( $config['archive'] ) ) {
			$config['archive'] = [
				'directory' => '',
			];
		}

		$skipErrors = ! empty( $args['skip-errors'] ) ? $args['skip-errors'] : false;
		$outputDir  = ! empty( $args['output-dir'] ) ? $args['output-dir'] : get_temp_dir();

		$composer = $this->getComposer( $config );

		$downloads = new ComposerArchiveBuilder( $io, $outputDir, $config, $skipErrors );
		$downloads->setComposer( $composer );

		return $downloads->dumpPackage( $package );
	}

	/**
	 * Determine the dynamic configuration depending on the received args.
	 *
	 * This is not the same as a local (file-based) Composer config. That is taken in to account,
	 * but this one will overwrite that one.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function getDynamicConfig( array $args ): array {
		// Start with the default config.
		$config = $this->getDefaultDynamicConfig();

		// Depending on the received args, make the config modifications.
		$config = $this->parseDynamicConfigArgs( $config, $args );

		// Allow others to filter this and add or modify the Composer client config (like adding OAuth tokens).
		return apply_filters( 'pixelgradelt_conductor/composer_client_config', $config, $args );
	}

	/**
	 * Given a config and a set of arguments, make the necessary config modifications.
	 *
	 * @param array $config The initial config.
	 * @param array $args
	 *
	 * @return array The modified config.
	 */
	protected function parseDynamicConfigArgs( array $config, array $args ): array {
		$originalConfig = $config;

		// Allow the passing of a full composer config.
		if ( ! empty( $args['config'] ) ) {
			$config = $args['config'];
		}

		if ( ! empty( $args['repositories'] ) ) {
			$config['repositories'] = $args['repositories'];
		}

		if ( ! empty( $args['require'] ) ) {
			$config['require'] = $args['require'];
		}

		if ( isset( $args['require-all'] ) ) {
			$config['require-all'] = $args['require-all'];
		}

		if ( isset( $args['require-dependencies'] ) ) {
			$config['require-dependencies'] = $args['require-dependencies'];
		}

		if ( isset( $args['require-dev-dependencies'] ) ) {
			$config['require-dev-dependencies'] = $args['require-dev-dependencies'];
		}

		if ( isset( $args['only-dependencies'] ) ) {
			$config['only-dependencies'] = $args['only-dependencies'];
		}

		if ( isset( $args['only-best-candidates'] ) ) {
			$config['only-best-candidates'] = $args['only-best-candidates'];
		}

		if ( isset( $args['minimum-stability'] ) ) {
			$config['minimum-stability'] = $args['minimum-stability'];
		}

		if ( isset( $args['minimum-stability-per-package'] ) ) {
			$config['minimum-stability-per-package'] = $args['minimum-stability-per-package'];
		}

		if ( isset( $args['ignore-platform-reqs'] ) ) {
			$config['ignore-platform-reqs'] = $args['ignore-platform-reqs'];
		}

		return apply_filters( 'pixelgradelt_conductor/composer_client_config_parse_args', $config, $args, $originalConfig );
	}

	public function getDefaultDynamicConfig(): array {
		$default_config = [
			'name'                      => 'pixelgradelt-conductor/fake_project',
			'repositories'              => [],
			'require-all'               => false,
			'require-dependencies'      => false,
			'require-dev-dependencies'  => false,
			'require-dependency-filter' => true,
			'minimum-stability'         => 'dev',
			'providers'                 => false,

			'prefer-stable' => true,
			'prefer-lowest' => false,
			// This is the default Composer config to pass when initializing Composer.
			'config'        => [],
		];

		// If we are in a local/development environment, relax further.
		if ( $this->is_debug_mode() ) {
			// Skip SSL verification since we may be using self-signed certificates.
			$default_config['disable-tls'] = true;
			$default_config['secure-http'] = false;
		}

		return apply_filters( 'pixelgradelt_conductor/composer_client_default_config', $default_config );
	}

	/**
	 * @param array|string|null $config Either a configuration array or a filename to read from, if null it will read from the default filename
	 *
	 * @return Composer
	 */
	public function getComposer( $config = null ): Composer {
		if ( null === $this->composer || $this->composer_config !== $config ) {
			try {
				$factory = new Factory();
				// We will set the Composer current working directory to our home directory, if provided.
				if ( ! empty( $this->composer_home_dir ) ) {
					// Make sure that the directory exists.
					wp_mkdir_p( $this->composer_home_dir );
				}
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
	 * @return IOInterface
	 */
	public function getIO() {
		if ( null === $this->io ) {
			$this->io = new BufferIO();
		}

		return $this->io;
	}

	/**
	 * @param BaseIO $io
	 */
	public function setIO( BaseIO $io ) {
		$this->io = $io;
	}

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
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	protected function is_debug_mode(): bool {
		return \defined( 'WP_DEBUG' ) && true === WP_DEBUG;
	}
}
