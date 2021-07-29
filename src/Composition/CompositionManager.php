<?php
/**
 * Composition management routines.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Composition;

use Automattic\Jetpack\Constants;
use Cedaro\WP\Plugin\AbstractHookProvider;
use Composer\Json\JsonFile;
use PixelgradeLT\Conductor\Composer\ComposerWrapperInterface;
use PixelgradeLT\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\ParsingException;
use function PixelgradeLT\Conductor\is_debug_mode;
use function PixelgradeLT\Conductor\is_dev_url;
use WP_Http as HTTP;
use const PixelgradeLT\Conductor\STORAGE_DIR;

/**
 * Class to manage the site composition.
 *
 * @since 0.1.0
 */
class CompositionManager extends AbstractHookProvider {

	/**
	 * Composer Installers WordPress plugin type.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const COMPOSER_WP_PLUGIN_TYPE = 'wordpress-plugin';

	/**
	 * Composer Installers WordPress theme type.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const COMPOSER_WP_THEME_TYPE = 'wordpress-theme';

	/**
	 * Composer Installers WordPress theme type.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const LTPART_PACKAGE_NAME_PATTERN = '/^pixelgradelt-records\/part_[a-z0-9]+(([_.]?|-{0,2})[a-z0-9]+)*$/';

	/**
	 * Composer.lock hash option name.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const COMPOSER_LOCK_HASH_OPTION_NAME = 'pixelgradelt_conductor_composer_lock_hash';

	/**
	 * The composition plugins option name.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const COMPOSITION_PLUGINS_OPTION_NAME = 'pixelgradelt_conductor_composition_plugins';

	/**
	 * The composition themes option name.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const COMPOSITION_THEMES_OPTION_NAME = 'pixelgradelt_conductor_composition_themes';

	/**
	 * Queue.
	 *
	 * @since 0.1.0
	 *
	 * @var QueueInterface
	 */
	protected QueueInterface $queue;

	/**
	 * Logger.
	 *
	 * @since 0.1.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Composer Wrapper.
	 *
	 * @since 0.8.0
	 *
	 * @var ComposerWrapperInterface
	 */
	protected ComposerWrapperInterface $composerWrapper;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param QueueInterface           $queue  Queue.
	 * @param LoggerInterface          $logger Logger.
	 * @param ComposerWrapperInterface $composerWrapper
	 */
	public function __construct(
		QueueInterface $queue,
		LoggerInterface $logger,
		ComposerWrapperInterface $composerWrapper
	) {
		$this->queue           = $queue;
		$this->logger          = $logger;
		$this->composerWrapper = $composerWrapper;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_recurring_events' );

		// For now, we will rely on CLI commands and server cron to do the check and updating.
		// add_action( 'pixelgradelt_conductor/midnight', [ $this, 'check_update' ] );
		// On updated composition, refresh the DB cache.
		$this->add_action( 'pixelgradelt_conductor/updated_composer_json', 'hook_refresh_composition_db_cache' );
		// On updated DB cache, schedule activate plugins and theme.
		$this->add_action( 'pixelgradelt_conductor/updated_composition_plugins_and_themes_cache', 'schedule_activate_composition_plugins_and_themes' );
		add_action( 'pixelgradelt_conductor/activate_composition_plugins_and_themes', [
			$this,
			'handle_composition_plugins_activation',
		], 20 );
		add_action( 'pixelgradelt_conductor/activate_composition_plugins_and_themes', [
			$this,
			'handle_composition_themes_activation',
		], 30 );
	}

	/**
	 * Get a composition's plugin data.
	 *
	 * Don't provide a plugin file path to get all plugins' data.
	 *
	 * @param string $plugin_file The plugin's file path identifier relative to the plugins folder.
	 *                            Leave empty to get all composition's plugins data.
	 *
	 * @return null|array The plugin data if a valid $plugin_file was provided.
	 *               null if the provided $plugin_file could not be found.
	 *               An associative array with all the composition's plugins if no $plugin_file was provided,
	 *               with each key being the plugin file path.
	 */
	public function get_composition_plugin( string $plugin_file = '' ): ?array {
		$cached_data = get_option( self::COMPOSITION_PLUGINS_OPTION_NAME, false );
		if ( false === $cached_data ) {
			$this->refresh_composition_db_cache();
			$cached_data = get_option( self::COMPOSITION_PLUGINS_OPTION_NAME );
		}

		if ( empty( $cached_data ) ) {
			if ( ! empty( $plugin_file ) ) {
				return null;
			}

			return [];
		}

		if ( ! empty( $plugin_file ) ) {
			if ( isset( $cached_data[ $plugin_file ] ) ) {
				return $cached_data[ $plugin_file ];
			}

			return null;
		}

		// Return all plugins data if no single plugin was targeted or found.
		return $cached_data;
	}

	/**
	 * Get a composition's theme data.
	 *
	 * Don't provide a theme directory to get all themes' data.
	 *
	 * @param string $theme_dir   The theme's directory name.
	 *                            Leave empty to get all composition's themes data.
	 *
	 * @return null|array The theme data if a valid $theme_dir was provided.
	 *               null if the provided $theme_dir could not be found.
	 *               An associative array with all the composition's themes if no $theme_dir was provided,
	 *               with each key being the theme directory name (stylesheet).
	 */
	public function get_composition_theme( string $theme_dir = '' ): ?array {
		$cached_data = get_option( self::COMPOSITION_THEMES_OPTION_NAME, false );
		if ( false === $cached_data ) {
			$this->refresh_composition_db_cache();
			$cached_data = get_option( self::COMPOSITION_THEMES_OPTION_NAME );
		}

		if ( empty( $cached_data ) ) {
			if ( ! empty( $theme_dir ) ) {
				return null;
			}

			return [];
		}

		if ( ! empty( $theme_dir ) ) {
			if ( isset( $cached_data[ $theme_dir ] ) ) {
				return $cached_data[ $theme_dir ];
			}

			return null;
		}

		return $cached_data;
	}

	/**
	 * Get the contents of the site's composer.json file.
	 *
	 * @param bool $debug Whether to log detailed exceptions (like stack traces and stuff).
	 *
	 * @return false|array
	 */
	public function get_composer_json( bool $debug = false ) {
		$composerJsonFile = new JsonFile( \path_join( LT_ROOT_DIR, 'composer.json' ) );
		if ( ! $composerJsonFile->exists() ) {
			$this->logger->error( 'The site\'s composer.json file doesn\'t exist.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}
		try {
			$composerJsonCurrentContents = $composerJsonFile->read();
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be read: {message}',
				[
					'message'     => $e->getMessage(),
					'exception'   => $debug ? $e : null,
					'logCategory' => 'composition',
				]
			);

			return false;
		} catch ( ParsingException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be parsed: {message}',
				[
					'message'     => $e->getMessage(),
					'exception'   => $debug ? $e : null,
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		return $composerJsonCurrentContents;
	}

	/**
	 * Get the absolute path to the site's composer.json file.
	 *
	 * @return string
	 */
	public function get_composer_json_path(): string {
		return \path_join( LT_ROOT_DIR, 'composer.json' );
	}

	/**
	 * Write the contents of the site's composer.json file.
	 *
	 * @param array $contents    The entire composer.json contents to write.
	 * @param int   $jsonOptions json_encode options (defaults to JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
	 * @param bool  $debug       Whether to log detailed exceptions (like stack traces and stuff).
	 *
	 * @return bool
	 */
	public function write_composer_json( array $contents, int $jsonOptions = 448, bool $debug = false ): bool {
		$composerJsonFile = new JsonFile( $this->get_composer_json_path() );
		try {
			$composerJsonFile->write( $contents, $jsonOptions );
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to write the site\'s composer.json file: {message}',
				[
					'message'     => $e->getMessage(),
					'exception'   => $debug ? $e : null,
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		return true;
	}

	/**
	 * Get the absolute path to the site's composer.json backup file.
	 *
	 * @return string
	 */
	public function get_composer_json_backup_path(): string {
		return \path_join( STORAGE_DIR, 'backup/composer.json.bak' );
	}

	/**
	 * Backup the contents of the site's composer.json file.
	 *
	 * @return false|string False on failure. The absolute path to the backup file on success.
	 */
	public function backup_composer_json() {
		$backup_file = $this->get_composer_json_backup_path();

		// First, make sure that the backup file exists since copy() doesn't do any recursive directory creation, etc.
		if ( ! file_exists( $backup_file ) ) {
			if ( ! wp_mkdir_p( \dirname( $backup_file ) ) ) {
				$this->logger->error( 'Failed to BACKUP the site\'s "composer.json" since we could not create the directory structure of the backup file ("{backupFile}").',
					[
						'backupFile'  => $backup_file,
						'logCategory' => 'composition',
					]
				);

				return false;
			}

			$temphandle = @fopen( $backup_file, 'w+' ); // @codingStandardsIgnoreLine.
			@fclose( $temphandle );                     // @codingStandardsIgnoreLine.

			if ( Constants::is_defined( 'FS_CHMOD_FILE' ) ) {
				@chmod( $backup_file, FS_CHMOD_FILE ); // @codingStandardsIgnoreLine.
			}
		}

		if ( ! copy( $this->get_composer_json_path(), $backup_file ) ) {
			$this->logger->error( 'Failed to BACKUP the site\'s "composer.json" to the backup file "{backupFile}" (could not copy).',
				[
					'backupFile'  => $backup_file,
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		return $backup_file;
	}

	/**
	 * Revert the contents of the site's composer.json file from the backup file.
	 *
	 * @param bool $ignore_missing Whether to ignore the fact that the site's composer.json is missing and create it from the backup.
	 *
	 * @return bool
	 */
	public function revert_composer_json( bool $ignore_missing = false ): bool {
		$composer_file = $this->get_composer_json_path();
		if ( ! file_exists( $composer_file ) ) {
			if ( ! $ignore_missing ) {
				$this->logger->error( 'Failed to REVERT the site\'s composer.json file from the backup file since the site\'s composer.json file is MISSING.',
					[
						'logCategory' => 'composition',
					]
				);

				return false;
			}

			// Create the site's composer.json file.
			$temphandle = @fopen( $composer_file, 'w+' ); // @codingStandardsIgnoreLine.
			@fclose( $temphandle );                       // @codingStandardsIgnoreLine.

			if ( Constants::is_defined( 'FS_CHMOD_FILE' ) ) {
				@chmod( $composer_file, FS_CHMOD_FILE ); // @codingStandardsIgnoreLine.
			}
		}

		$backup_file = $this->get_composer_json_backup_path();
		if ( ! file_exists( $backup_file ) ) {
			$this->logger->error( 'Could not REVERT the site\'s composer.json file since the backup file could not be found ("{backupFile}").',
				[
					'backupFile'  => $backup_file,
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		if ( ! copy( $backup_file, $this->get_composer_json_path() ) ) {
			$this->logger->error( 'Failed to REVERT the site\'s composer.json file from the backup file (could not copy).',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		return true;
	}

	/**
	 * Check with LT Records if the current site composition is valid, should be updated, and update it if LT Records provides updated contents.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $skip_write Whether to skip writing the updated composition contents to composer.json.
	 * @param bool $debug      Whether to log detailed exceptions (like stack traces and stuff).
	 * @param bool $silent     Whether to trigger actions or not.
	 *
	 * @return bool
	 */
	public function check_update( bool $skip_write = false, bool $debug = false, bool $silent = false ): bool {
		if ( ! defined( 'LT_RECORDS_API_KEY' ) || empty( LT_RECORDS_API_KEY )
		     || ! defined( 'LT_RECORDS_API_PWD' ) || empty( LT_RECORDS_API_PWD )
		     || ! defined( 'LT_RECORDS_COMPOSITION_REFRESH_URL' ) || empty( LT_RECORDS_COMPOSITION_REFRESH_URL )
		) {
			$this->logger->warning( 'Could not check for composition update with LT Records because there are missing or empty environment variables.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		// Read the current contents of the site's composer.json (the composition).
		if ( ! $composerJsonCurrentContents = $this->get_composer_json( $debug ) ) {
			$this->logger->warning( 'Could not read the site\'s composer.json file contents.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		$request_args = [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( LT_RECORDS_API_KEY . ':' . LT_RECORDS_API_PWD ),
			],
			'timeout'   => 5,
			'sslverify' => ! ( is_debug_mode() || is_dev_url( LT_RECORDS_COMPOSITION_REFRESH_URL ) ),
			// Do the json_encode ourselves so it maintains types. Note the added Content-Type header also.
			'body'      => json_encode( [
				'composer' => $composerJsonCurrentContents,
			] ),
		];

		$response = wp_remote_post( LT_RECORDS_COMPOSITION_REFRESH_URL, $request_args );
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'The composition update check with LT Records failed with code "{code}": {message}',
				[
					'code'        => $response->get_error_code(),
					'message'     => $response->get_error_message(),
					'data'        => $response->get_error_data(),
					'logCategory' => 'composition',
				]
			);

			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) >= HTTP::BAD_REQUEST ) {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$accepted_keys = array_fill_keys( [ 'code', 'message', 'data' ], '' );
			$body          = array_replace( $accepted_keys, array_intersect_key( $body, $accepted_keys ) );
			if ( 'rest_invalid_fingerprint' === $body['code'] ) {
				$this->logger->error( 'The composition update check with LT Records failed with code "{code}". Most likely, this means that the composer.json file was EDITED MANUALLY!',
					[
						'code'        => $body['code'],
						'message'     => $body['message'],
						'data'        => $body['data'],
						'logCategory' => 'composition',
					]
				);
			} else {
				$this->logger->error( 'The composition update check with LT Records failed with code "{code}": {message}',
					[
						'code'        => $body['code'],
						'message'     => $body['message'],
						'data'        => $body['data'],
						'logCategory' => 'composition',
					]
				);
			}

			return false;
		}

		// If we have nothing to update, bail.
		if ( wp_remote_retrieve_response_code( $response ) === HTTP::NO_CONTENT ) {
			$this->logger->info( 'The site\'s composition (composer.json file) doesn\'t need updating.',
				[
					'logCategory' => 'composition',
				]
			);

			return true;
		}

		// We get back the entire composer.json contents.
		$receivedComposerJson = json_decode( wp_remote_retrieve_body( $response ), true );

		$jsonOptions = JsonFile::JSON_UNESCAPED_SLASHES | JsonFile::JSON_PRETTY_PRINT | JsonFile::JSON_UNESCAPED_UNICODE;

		// Double check if we should actually update.
		// We need to ignore the time entry since that represents the time LT Records generated the composition.
		// Most of the time it is the current time() and would lead to update without the need to.
		$tempComposerJsonCurrentContents = $composerJsonCurrentContents;
		unset( $tempComposerJsonCurrentContents['time'] );
		$currentContent           = JsonFile::encode( $tempComposerJsonCurrentContents, $jsonOptions ) . ( $jsonOptions & JsonFile::JSON_PRETTY_PRINT ? "\n" : '' );
		$tempReceivedComposerJson = $receivedComposerJson;
		unset( $tempReceivedComposerJson['time'] );
		$newContent = JsonFile::encode( $tempReceivedComposerJson, $jsonOptions ) . ( $jsonOptions & JsonFile::JSON_PRETTY_PRINT ? "\n" : '' );
		if ( $currentContent === $newContent ) {
			$this->logger->info( 'The site\'s composition (composer.json file) doesn\'t need updating.',
				[
					'logCategory' => 'composition',
				]
			);

			return true;
		}

		// Now we need to prepare the new contents and write them (if needed) the same way Composer does it.
		if ( ! $skip_write ) {
			// Better safe than sorry.
			$this->backup_composer_json();

			if ( ! $this->write_composer_json( $receivedComposerJson, $jsonOptions, $debug ) ) {
				$this->logger->error( 'The site\'s composer.json file could not be written with the LT Records updated contents.',
					[
						'logCategory' => 'composition',
					]
				);

				return false;
			}

			$this->logger->info( 'The site\'s composer.json contents have been UPDATED with the contents provided by LT Records.',
				[
					'logCategory' => 'composition',
				]
			);

			if ( ! $silent ) {
				/**
				 * After the composer.json has been updated.
				 *
				 * @since 0.1.0
				 *
				 * @param array $newContents The written composer.json data.
				 * @param array $oldContents The previous composer.json data.
				 */
				do_action( 'pixelgradelt_conductor/updated_composer_json', $receivedComposerJson, $composerJsonCurrentContents );
			}
		} else {
			$this->logger->warning( 'The site\'s composer.json contents need to be UPDATED according to LT Records.',
				[
					'logCategory' => 'composition',
				] + ( $debug ? [ 'newComposerJson' => $receivedComposerJson ] : [] )
			);

			return false;
		}

		return true;
	}

	/**
	 * Based on the composition's LT details reinitialise the site's composition.
	 *
	 * The LT details are stored under the "extra" entry of composer.json.
	 * We are only interested in "lt-composition" (the encrypted LT details)
	 * since that is what we need to initialize a composition.
	 *
	 * After a reinitialisation it is best to run an update check to get the composition up-to-speed.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $debug Whether to log detailed exceptions (like stack traces and stuff).
	 * @param bool $silent     Whether to trigger actions or not.
	 *
	 * @return bool
	 */
	public function reinitialise( bool $debug = false, bool $silent = false ): bool {
		if ( ! defined( 'LT_RECORDS_API_KEY' ) || empty( LT_RECORDS_API_KEY )
		     || ! defined( 'LT_RECORDS_API_PWD' ) || empty( LT_RECORDS_API_PWD )
		     || ! defined( 'LT_RECORDS_COMPOSITION_CREATE_URL' ) || empty( LT_RECORDS_COMPOSITION_CREATE_URL )
		) {
			$this->logger->warning( 'Could not check for composition update with LT Records because there are missing or empty environment variables.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		// Read the current contents of the site's composer.json (the composition).
		if ( ! $composerJsonCurrentContents = $this->get_composer_json( $debug ) ) {
			$this->logger->warning( 'Could not read the site\'s composer.json file contents.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		if ( empty( $composerJsonCurrentContents['extra']['lt-composition'] ) ) {
			$this->logger->warning( 'The site\'s composition (composer.json file) doesn\'t have the encrypted LT details (["extra"]["lt-composition"]).',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		$request_args = [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( LT_RECORDS_API_KEY . ':' . LT_RECORDS_API_PWD ),
			],
			'timeout'   => 5,
			'sslverify' => ! ( is_debug_mode() || is_dev_url( LT_RECORDS_COMPOSITION_CREATE_URL ) ),
			// Do the json_encode ourselves so it maintains types. Note the added Content-Type header also.
			'body'      => json_encode( [
				'ltdetails' => $composerJsonCurrentContents['extra']['lt-composition'],
			] ),
		];

		$response = wp_remote_post( LT_RECORDS_COMPOSITION_CREATE_URL, $request_args );
		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'The empty composition creation by LT Records failed with code "{code}": {message}',
				[
					'code'        => $response->get_error_code(),
					'message'     => $response->get_error_message(),
					'data'        => $response->get_error_data(),
					'logCategory' => 'composition',
				]
			);

			return false;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== HTTP::CREATED ) {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$accepted_keys = array_fill_keys( [ 'code', 'message', 'data' ], '' );
			$body          = array_replace( $accepted_keys, array_intersect_key( $body, $accepted_keys ) );
			$this->logger->error( 'The empty composition creation by LT Records failed with code "{code}": {message}',
				[
					'code'        => $body['code'],
					'message'     => $body['message'],
					'data'        => $body['data'],
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		// We get back the "empty" composer.json contents.
		$receivedComposerJson = json_decode( wp_remote_retrieve_body( $response ), true );

		$jsonOptions = JsonFile::JSON_UNESCAPED_SLASHES | JsonFile::JSON_PRETTY_PRINT | JsonFile::JSON_UNESCAPED_UNICODE;
		if ( ! $this->write_composer_json( $receivedComposerJson, $jsonOptions, $debug ) ) {
			$this->logger->error( 'The site\'s composer.json file could not be written with the LT Records updated contents.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		$this->logger->info( 'The site\'s composer.json has been replaced with a basic, "empty" composition.',
			[
				'logCategory' => 'composition',
			]
		);

		if ( ! $silent ) {
			/**
			 * After the composer.json has been reinitialised.
			 *
			 * @since 0.1.0
			 *
			 * @param array $newContents The written composer.json data.
			 * @param array $oldContents The previous composer.json data.
			 */
			do_action( 'pixelgradelt_conductor/reinitialised_composer_json', $receivedComposerJson, $composerJsonCurrentContents );
		}

		return true;
	}

	/**
	 * Installs all the packages currently in the composition (composer.lock).
	 *
	 * Uses a Composer wrapper to run the same logic as the CLI command `composer install`,
	 * meaning no changes are made to the composer.lock file even if there are changes in composer.json.
	 *
	 * @since 0.8.0
	 *
	 * @param string $composer_json_path Optional. Absolute path to the composer.json path to use.
	 * @param array  $args               Optional. Install arguments.
	 *
	 * @return bool True on success. False on failure.
	 */
	public function composer_install( string $composer_json_path = '', array $args = [] ): bool {
		$args = \wp_parse_args( $args, [
			'update'              => false,
			'revert'              => false,
			'revert-file-path'    => '',
			'dry-run'             => false,
			'dev-mode'            => false,
			'dump-autoloader'     => true,
			'optimize-autoloader' => true,
			'verbose'             => false,
			'output-progress'     => false,
			'debug'               => false,
		] );

		if ( empty( $composer_json_path ) ) {
			$composer_json_path = $this->get_composer_json_path();

			// If instructed to revert in case of errors but not given a backup file path, fallback to the default backup file path.
			if ( $args['revert'] && empty( $args['revert-file-path'] ) ) {
				$args['revert-file-path'] = $this->get_composer_json_backup_path();
			}

			// Do not allow the use of non-existent backup files.
			if ( ! empty( $args['revert-file-path'] ) && ! file_exists( $args['revert-file-path'] ) ) {
				$args['revert']           = false;
				$args['revert-file-path'] = false;
			}
		}

		return $this->composerWrapper->install( $composer_json_path, $args );
	}

	/**
	 * Updates all the packages currently in the composition (composer.json).
	 *
	 * Uses a Composer wrapper to run the same logic as the CLI command `composer install`,
	 * meaning existing packages get updated, removed packages get removed, and missing packages get installed.
	 *
	 * What is different from `composer update` is that, in case of error, we can revert the composer.json to a backed up version.
	 *
	 * @since 0.8.0
	 *
	 * @param string $composer_json_path Optional. Absolute path to the composer.json path to use.
	 * @param array  $args               Optional. Update arguments.
	 *
	 * @return bool True on success. False on failure.
	 */
	public function composer_update( string $composer_json_path = '', array $args = [] ): bool {
		// Force Composer to work in update mode.
		$args['update'] = true;

		return $this->composer_install( $composer_json_path, $args );
	}

	/**
	 * Maybe schedule the recurring actions/events, if it is not already scheduled.
	 *
	 * @since 0.1.0
	 */
	protected function schedule_recurring_events() {
		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pixelgradelt_conductor/midnight', [], 'plt_con' );
		}

		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pixelgradelt_conductor/hourly', [], 'plt_con' );
		}
	}

	/**
	 * Schedule the async event to attempt to activate all the plugins and themes registered in the composition.
	 *
	 * @since 0.1.0
	 */
	protected function schedule_activate_composition_plugins_and_themes() {
		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/activate_composition_plugins_and_themes' ) ) {
			$this->queue->schedule_single( time(), 'pixelgradelt_conductor/activate_composition_plugins_and_themes', [], 'pixelgrade-conductor' );
		}
	}

	/**
	 * Activate all plugins installed via the composition.
	 *
	 * Of course, must-use or drop-in plugins don't get activated.
	 *
	 * @return bool True if there were no errors on activation. False if some plugins could not be activated.
	 */
	public function handle_composition_plugins_activation(): bool {
		$plugins        = $this->get_composition_plugin();
		$errors         = [];
		$already_active = [];
		if ( ! empty( $plugins ) ) {
			foreach ( $plugins as $plugin_file => $plugin_data ) {
				if ( \is_plugin_active( $plugin_file ) ) {
					$already_active[ $plugin_file ] = $plugin_data;
					continue;
				}

				$result = \activate_plugin( $plugin_file );
				if ( $result instanceof \WP_Error ) {
					if ( 'plugin_not_found' === $result->get_error_code() ) {
						// We will silently deactivate it to prevent the user notice regarding plugin not found.
						\deactivate_plugins( $plugin_file, true );

						$this->logger->warning( 'Encountered MISSING composition PLUGIN "{plugin_name}" ({plugin_file}), corresponding to package "{plugin_package} v{plugin_package_version}". Silently deactivated it.',
							[
								'plugin_name'            => $plugin_data['name'],
								'plugin_file'            => $plugin_data['plugin-file'],
								'plugin_package'         => $plugin_data['package-name'],
								'plugin_package_version' => $plugin_data['version'],
								'logCategory'            => 'composition',
							]
						);

						continue;
					}

					$errors[ $plugin_file ] = $result;
					$this->logger->error( 'The composition\'s PLUGIN ACTIVATION failed with "{code}": {message}',
						[
							'code'        => $result->get_error_code(),
							'message'     => $result->get_error_message(),
							'data'        => $result->get_error_data(),
							'logCategory' => 'composition',
						]
					);

					continue;
				}

				$this->logger->info( 'The composition\'s PLUGIN "{plugin_name}" ({plugin_file}), corresponding to package "{plugin_package} v{plugin_package_version}", was automatically ACTIVATED.',
					[
						'plugin_name'            => $plugin_data['name'],
						'plugin_file'            => $plugin_data['plugin-file'],
						'plugin_package'         => $plugin_data['package-name'],
						'plugin_package_version' => $plugin_data['version'],
						'logCategory'            => 'composition',
					]
				);
			}
		}

		if ( ! empty( $already_active ) && ! empty( $plugins ) && count( $already_active ) === count( $plugins ) ) {
			$this->logger->info( 'No plugin needed to be activated.',
				[
					'logCategory' => 'composition',
				]
			);
		}

		if ( ! empty( $already_active ) ) {
			$message = 'The following composition PLUGINS were already active:' . PHP_EOL;
			foreach ( $already_active as $plugin_file => $plugin_data ) {
				$message .= '    - ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}

		// Since there might be removed plugin packages we instruct WordPress to validate the active plugins (it will silently deactivate missing plugins).
		$invalid_plugins = validate_active_plugins();
		if ( ! empty( $invalid_plugins ) ) {
			$message = 'The following active PLUGINS were found INVALID and deactivated:' . PHP_EOL;
			foreach ( $invalid_plugins as $plugin_file => $error ) {
				$message .= '    - "' . $plugin_file . '" due to: ' . $error->get_error_message() . PHP_EOL;
			}
			$message .= 'This might not be a reason to worry about since composition plugins that get removed from the composition will "suddenly disappear" and be identified as INVALID (missing).' . PHP_EOL;

			$this->logger->warning( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}

		return empty( $errors );
	}

	/**
	 * @return bool True if there were no
	 */
	public function handle_composition_themes_activation(): bool {
		// For themes, the logic is somewhat more convoluted since we can only have a single theme active at any one time.
		// Also, the user might bring his or hers own themes (or child-themes).
		// So, we will only force activate if one of the core themes is active.
		// @todo Maybe explore a more enforceable path to activate LT Theme(s).
		$themes = $this->get_composition_theme();
		if ( ! empty( $themes ) ) {
			// Get the currently active theme.
			$current_theme = \wp_get_theme();

			$default_core_theme = \WP_Theme::get_core_default_theme();

			// If we have a core theme active, we can proceed.
			if ( ( false !== $default_core_theme && $current_theme->get_stylesheet() === $default_core_theme->get_stylesheet() )
			     || preg_match( '/^twenty/i', $current_theme->get_stylesheet() )
			     || 'the wordpress team' === trim( strtolower( $current_theme->get( 'Author' ) ) ) ) {

				$theme_to_activate = false;
				// Search for a child theme to activate.
				foreach ( $themes as $theme_dir => $theme_data ) {
					// Better safe than sorry.
					if ( $theme_dir === $current_theme->get_stylesheet() ) {
						continue;
					}

					// If we find a child theme, we need to make sure that we have the parent available also.
					if ( $theme_data['child-theme'] && isset( $themes[ $theme_data['template'] ] ) ) {
						$theme_to_activate = $theme_data;

						break;
					}
				}

				if ( $theme_to_activate ) {
					$requirements = \validate_theme_requirements( $theme_to_activate['stylesheet'] );
					if ( \is_wp_error( $requirements ) ) {
						$this->logger->error( 'Found CHILD-THEME "{theme_name}" ({theme_dir}), corresponding to package "{theme_package} v{theme_package_version}", in the composition but we couldn\'t activate it due to "{code}": {message}',
							[
								'code'                  => $requirements->get_error_code(),
								'message'               => $requirements->get_error_message(),
								'theme_name'            => $theme_to_activate['name'],
								'theme_dir'             => $theme_to_activate['stylesheet'],
								'theme_package'         => $theme_to_activate['package-name'],
								'theme_package_version' => $theme_to_activate['version'],
								'data'                  => $requirements->get_error_data(),
								'logCategory'           => 'composition',
							]
						);

						$theme_to_activate = false;
					} else {
						\switch_theme( $theme_to_activate['stylesheet'] );
					}
				}

				if ( ! $theme_to_activate ) {
					// Search for a regular/parent theme to activate.
					foreach ( $themes as $theme_dir => $theme_data ) {
						// Better safe than sorry.
						if ( $theme_dir === $current_theme->get_stylesheet() ) {
							continue;
						}

						// Exclude child-themes.
						if ( $theme_data['child-theme'] ) {
							continue;
						}

						$theme_to_activate = $theme_data;
					}

					if ( $theme_to_activate ) {
						$requirements = \validate_theme_requirements( $theme_to_activate['stylesheet'] );
						if ( \is_wp_error( $requirements ) ) {
							$this->logger->error( 'Found THEME "{theme_name}" ({theme_dir}), corresponding to package "{theme_package} v{theme_package_version}", in the composition but we couldn\'t activate it due to "{code}": {message}',
								[
									'code'                  => $requirements->get_error_code(),
									'message'               => $requirements->get_error_message(),
									'theme_name'            => $theme_to_activate['name'],
									'theme_dir'             => $theme_to_activate['stylesheet'],
									'theme_package'         => $theme_to_activate['package-name'],
									'theme_package_version' => $theme_to_activate['version'],
									'data'                  => $requirements->get_error_data(),
									'logCategory'           => 'composition',
								]
							);

							$theme_to_activate = false;
						} else {
							\switch_theme( $theme_to_activate['stylesheet'] );
						}
					}
				}

				if ( $theme_to_activate ) {
					$this->logger->info( 'The composition\'s THEME "{theme_name}" ({theme_dir}), corresponding to package "{theme_package} v{theme_package_version}", was automatically ACTIVATED.',
						[
							'theme_name'            => $theme_to_activate['name'],
							'theme_dir'             => $theme_to_activate['stylesheet'],
							'theme_package'         => $theme_to_activate['package-name'],
							'theme_package_version' => $theme_to_activate['version'],
							'logCategory'           => 'composition',
						]
					);
				}
			}
		}

		// Get the currently active theme.
		$current_theme = \wp_get_theme();

		// There was no reason to active since the current theme is OK.
		if ( ! isset( $theme_to_activate ) ) {
			$this->logger->info( 'No need to change the active theme since the current active theme "{theme_name}" ({theme_dir}) is not a core theme.',
				[
					'theme_name'  => $current_theme->get( 'Name' ),
					'theme_dir'   => $current_theme->get_stylesheet(),
					'logCategory' => 'composition',
				]
			);

			return true;
		}

		// Since there might be removed theme packages we instruct WordPress to validate the current theme (it will silently fallback to the default theme).
		/** Do the checks done by @see \validate_current_theme() */
		if ( ! file_exists( get_template_directory() . '/index.php' ) ) {
			// Invalid.
			$this->logger->warning( 'The current active theme "{theme_name}" ({theme_dir}) was found INVALID because it{parent} is missing the "index.php" file.',
				[
					'theme_name'  => $current_theme->get( 'Name' ),
					'theme_dir'   => $current_theme->get_stylesheet(),
					'parent'      => is_child_theme() ? '\'s PARENT THEME' : '',
					'logCategory' => 'composition',
				]
			);
		} elseif ( ! file_exists( get_template_directory() . '/style.css' ) ) {
			// Invalid.
			$this->logger->warning( 'The current active theme "{theme_name}" ({theme_dir}) was found INVALID because it{parent} is missing the "style.css" file.',
				[
					'theme_name'  => $current_theme->get( 'Name' ),
					'theme_dir'   => $current_theme->get_stylesheet(),
					'parent'      => is_child_theme() ? '\'s PARENT THEME' : '',
					'logCategory' => 'composition',
				]
			);
		} elseif ( is_child_theme() && ! file_exists( get_stylesheet_directory() . '/style.css' ) ) {
			// Invalid.
			$this->logger->warning( 'The current active child-theme "{theme_name}" ({theme_dir}) was found INVALID because it\'s missing the "style.css" file.',
				[
					'theme_name'  => $current_theme->get( 'Name' ),
					'theme_dir'   => $current_theme->get_stylesheet(),
					'logCategory' => 'composition',
				]
			);
		} else {
			// Valid.
		}
		// Run the core validation to fallback on a core theme.
		if ( ! validate_current_theme() ) {
			$this->logger->info( 'Since the current active theme "{theme_name}" ({theme_dir}) was found INVALID, silently fallback to the core default theme.',
				[
					'theme_name'  => $current_theme->get( 'Name' ),
					'theme_dir'   => $current_theme->get_stylesheet(),
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		if ( ! $theme_to_activate ) {
			return false;
		}

		return true;
	}

	/**
	 * Check the `composer.lock` file for modifications and update the data we cache about it (e.g. included plugins and themes).
	 *
	 * @since 0.1.0
	 *
	 * @param bool $force  Force the cache update regardless of efficiency checks.
	 * @param bool $debug  Whether to log detailed exceptions (like stack traces and stuff).
	 * @param bool $silent Whether to trigger actions or not.
	 *
	 * @return bool
	 */
	public function refresh_composition_db_cache( bool $force = false, bool $debug = false, bool $silent = false ): bool {
		// Read the current contents of the site's composer.lock.
		$composerLockJsonFile = new JsonFile( \path_join( LT_ROOT_DIR, 'composer.lock' ) );
		if ( ! $composerLockJsonFile->exists() ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t exist.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}
		try {
			$composerLockJsonCurrentContents = $composerLockJsonFile->read();
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'The site\'s composer.lock file could not be read: {message}',
				[
					'message'     => $e->getMessage(),
					'exception'   => $debug ? $e : null,
					'logCategory' => 'composition',
				]
			);

			return false;
		} catch ( ParsingException $e ) {
			$this->logger->error( 'The site\'s composer.lock file could not be parsed: {message}',
				[
					'message'     => $e->getMessage(),
					'exception'   => $debug ? $e : null,
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		if ( empty( $composerLockJsonCurrentContents['content-hash'] ) ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t have a "content-hash" entry.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		if ( empty( $composerLockJsonCurrentContents['packages'] ) ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t have any installed packages.',
				[
					'logCategory' => 'composition',
				]
			);

			return false;
		}

		// Check if the "content-hash" is different than what we have.
		// If they are the same, we don't need to update anything.
		if ( ! $force && get_option( self::COMPOSER_LOCK_HASH_OPTION_NAME ) === $composerLockJsonCurrentContents['content-hash'] ) {
			$this->logger->info( 'The site\'s composer.lock file hasn\'t changed. Skipping the cache update.',
				[
					'logCategory' => 'composition',
				]
			);

			return true;
		}

		// First, clear the WordPress plugins and themes cache
		// since WordPress can't know when Composer installs or updates a plugin or theme.
		\wp_clean_plugins_cache();
		\wp_clean_themes_cache();

		// Get the old plugins  and themes installed by Composer.
		$old_plugins = get_option( self::COMPOSITION_PLUGINS_OPTION_NAME, [] );
		$old_themes  = get_option( self::COMPOSITION_THEMES_OPTION_NAME, [] );

		// Gather the current plugins and themes installed by Composer.
		$plugins = [];
		$themes  = [];
		foreach ( $composerLockJsonCurrentContents['packages'] as $package ) {
			if ( $package['type'] === self::COMPOSER_WP_PLUGIN_TYPE ) {
				$plugin_data = $this->get_plugin_package_data( $package );
				if ( $plugin_data ) {
					$plugins = array_merge( $plugins, $plugin_data );
				}
			} else if ( $package['type'] === self::COMPOSER_WP_THEME_TYPE ) {
				$theme_data = $this->get_theme_package_data( $package );
				if ( $theme_data ) {
					$themes = array_merge( $themes, $theme_data );
				}
			}
		}

		/**
		 * Log what has actually happened with plugins
		 */
		$removed_plugins = array_diff_key( $old_plugins, $plugins );
		$added_plugins   = array_diff_key( $plugins, $old_plugins );
		$updated_plugins = array_intersect_key( $plugins, $old_plugins );
		$same_plugins    = [];
		foreach ( $updated_plugins as $key => $plugin_data ) {
			if ( \version_compare( $plugin_data['version'], $old_plugins[ $key ]['version'], '=' ) ) {
				$same_plugins[ $key ] = $plugin_data;
				unset ( $updated_plugins[ $key ] );
			}
		}

		if ( ! empty( $removed_plugins ) ) {
			$message = 'The following PLUGINS have been REMOVED, according to composer.lock:' . PHP_EOL;
			foreach ( $removed_plugins as $plugin_file => $plugin_data ) {
				$message .= '    - ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $added_plugins ) ) {
			$message = 'The following PLUGINS have been ADDED, according to composer.lock:' . PHP_EOL;
			foreach ( $added_plugins as $plugin_file => $plugin_data ) {
				$message .= '    - ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $updated_plugins ) ) {
			$message = 'The following PLUGINS have been UPDATED, according to composer.lock:' . PHP_EOL;
			foreach ( $updated_plugins as $plugin_file => $plugin_data ) {
				$message .= '    - ' . $plugin_data['name'] . ' (' . $plugin_file . ') - from version v' . $old_plugins[ $plugin_file ]['version'] . ' to version v' . $plugins[ $plugin_file ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $same_plugins ) ) {
			$message = 'The following PLUGINS remained the same, according to composer.lock:' . PHP_EOL;
			foreach ( $same_plugins as $plugin_file => $plugin_data ) {
				$message .= '    - ' . $plugin_data['name'] . ' (' . $plugin_file . ') - version v' . $plugins[ $plugin_file ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}

		/**
		 * Log what has actually happened with themes
		 */
		$removed_themes = array_diff_key( $old_themes, $themes );
		$added_themes   = array_diff_key( $themes, $old_themes );
		$updated_themes = array_intersect_key( $themes, $old_themes );
		$same_themes    = [];
		foreach ( $updated_themes as $key => $theme_data ) {
			if ( \version_compare( $theme_data['version'], $old_themes[ $key ]['version'], '=' ) ) {
				$same_themes[ $key ] = $theme_data;
				unset ( $updated_themes[ $key ] );
			}
		}

		if ( ! empty( $removed_themes ) ) {
			$message = 'The following THEMES have been REMOVED, according to composer.lock:' . PHP_EOL;
			foreach ( $removed_themes as $stylesheet => $theme_data ) {
				$message .= '    - ' . $theme_data['name'] . ' (' . $stylesheet . ') - v' . $theme_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $added_themes ) ) {
			$message = 'The following THEMES have been ADDED, according to composer.lock:' . PHP_EOL;
			foreach ( $added_themes as $stylesheet => $theme_data ) {
				$message .= '    - ' . $theme_data['name'] . ' (' . $stylesheet . ') - v' . $theme_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $updated_themes ) ) {
			$message = 'The following THEMES have been UPDATED, according to composer.lock:' . PHP_EOL;
			foreach ( $updated_themes as $stylesheet => $theme_data ) {
				$message .= '    - ' . $theme_data['name'] . ' (' . $stylesheet . ') - from version v' . $old_themes[ $stylesheet ]['version'] . ' to version v' . $themes[ $stylesheet ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}
		if ( ! empty( $same_themes ) ) {
			$message = 'The following THEMES have remained the same, according to composer.lock:' . PHP_EOL;
			foreach ( $same_themes as $stylesheet => $theme_data ) {
				$message .= '    - ' . $theme_data['name'] . ' (' . $stylesheet . ') - version v' . $themes[ $stylesheet ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message,
				[
					'logCategory' => 'composition',
				]
			);
		}

		// Save the plugins and themes data.
		update_option( self::COMPOSITION_PLUGINS_OPTION_NAME, $plugins, true );
		update_option( self::COMPOSITION_THEMES_OPTION_NAME, $themes, true );
		update_option( self::COMPOSER_LOCK_HASH_OPTION_NAME, $composerLockJsonCurrentContents['content-hash'], true );

		if ( ! $silent ) {
			/**
			 * After the composition's plugins and themes list has been updated.
			 *
			 * @since 0.1.0
			 *
			 * @param array $plugins The current composition plugins data.
			 * @param array $themes  The current composition themes data.
			 */
			do_action( 'pixelgradelt_conductor/updated_composition_plugins_and_themes_cache', $plugins, $themes );
		}

		return true;
	}

	protected function hook_refresh_composition_db_cache() {
		$this->refresh_composition_db_cache();
	}

	/**
	 * Clear the data we cache about it (e.g. included plugins and themes).
	 *
	 * @since 0.1.0
	 *
	 * @param bool $debug  Whether to log detailed exceptions (like stack traces and stuff).
	 * @param bool $silent Whether to trigger actions or not.
	 *
	 * @return bool
	 */
	public function clear_composition_db_cache( bool $debug = false, bool $silent = false ): bool {

		// Clear the cache
		delete_option( self::COMPOSITION_PLUGINS_OPTION_NAME );
		delete_option( self::COMPOSITION_THEMES_OPTION_NAME );
		delete_option( self::COMPOSER_LOCK_HASH_OPTION_NAME );

		$this->logger->info( 'The site\'s composition cache has been CLEARED.',
			[
				'logCategory' => 'composition',
			]
		);

		if ( ! $silent ) {
			/**
			 * After the composition's plugins and themes list has been cleared.
			 *
			 * @since 0.1.0
			 */
			do_action( 'pixelgradelt_conductor/cleared_composition_plugins_and_themes_cache' );
		}

		return true;
	}

	/**
	 * Get the data of a given plugin package.
	 *
	 * @since 0.1.0
	 *
	 * @param array $package The package data.
	 *
	 * @return array[]|null null if the plugin could not be found or a proper plugin file (with headers) couldn't be identified.
	 *                      A single entry associative array on success. The key is the plugin file path relative to the plugins folder.
	 */
	protected function get_plugin_package_data( array $package ): ?array {
		// Extract the package individual name (without the vendor). This represents the plugin folder.
		$plugin_folder = trim( substr( $package['name'], strpos( $package['name'], '/' ) + 1 ), '/' );
		if ( empty( $plugin_folder ) ) {
			$this->logger->error( 'Encountered invalid package name in composer.lock: {packageName}',
				[
					'packageName' => $package['name'],
					'logCategory' => 'composition',
				]
			);

			return null;
		}

		$plugin_data = $this->get_plugin_data( $plugin_folder );
		// This means we couldn't find the plugin.
		if ( ! $plugin_data ) {
			$this->logger->warning( 'Encountered WP plugin "{packageName}" in composer.lock for which we couldn\'t extract the plugin data.',
				[
					'packageName' => $package['name'],
					'logCategory' => 'composition',
				]
			);

			return null;
		}

		// The main plugin filename (the one that has the plugin headers).
		$plugin_main_file = array_keys( $plugin_data )[0];
		$plugin_data      = reset( $plugin_data );
		// The plugin file patch relative to the plugins directory (unique identifier of the plugin throughout WordPress).
		$plugin_file = path_join( $plugin_folder, $plugin_main_file );

		// Determine if this plugin is a LT Part plugin.
		$is_lt_part_plugin = false;
		if ( preg_match( self::LTPART_PACKAGE_NAME_PATTERN, $package['name'] ) ) {
			$is_lt_part_plugin = true;
		}

		return [
			$plugin_file => [
				'name'          => $plugin_data['Name'] ?? $package['name'],
				'plugin-file'   => $plugin_file,
				'package-name'  => $package['name'],
				'version'       => $package['version'],
				'description'   => $package['description'] ?? $plugin_data['Description'],
				'homepage'      => $package['homepage'] ?? $plugin_data['PluginURI'],
				'authors'       => $package['authors'] ??
				                   ( ! empty( $plugin_data['Author'] ) ?
					                   [
						                   [
							                   'name'     => $plugin_data['Author'],
							                   'homepage' => $plugin_data['AuthorURI'],
						                   ],
					                   ]
					                   :
					                   [] ),
				'ltpart-plugin' => $is_lt_part_plugin,
			],
		];
	}

	/**
	 * Get the plugin data in a given plugin folder.
	 *
	 * @since 0.1.0
	 *
	 * @see   \get_plugin_data()
	 *
	 * @param string $plugin_folder The plugin folder name.
	 *
	 * @return array[]|null null if the plugin could not be found or a proper plugin file (with headers) couldn't be identified.
	 *                      A single entry associative array on success. The key is the main plugin file and the data is the plugin's header data.
	 */
	protected function get_plugin_data( string $plugin_folder ): ?array {
		$plugin_data = \get_plugins( '/' . trim( $plugin_folder, '/' ) );
		if ( empty( $plugin_data ) ) {
			return null;
		}

		return $plugin_data;
	}

	/**
	 * Get the data of a given theme package.
	 *
	 * @since 0.1.0
	 *
	 * @param array $package The package data.
	 *
	 * @return array[]|null null if the theme could not be found or a proper theme stylesheet (with headers) couldn't be identified.
	 *                      A single entry associative array on success. The key is the theme stylesheet.
	 */
	protected function get_theme_package_data( array $package ): ?array {
		// Extract the package individual name (without the vendor). This represents the theme folder.
		$theme_folder = trim( substr( $package['name'], strpos( $package['name'], '/' ) + 1 ), '/' );
		if ( empty( $theme_folder ) ) {
			$this->logger->error( 'Encountered invalid package name in composer.lock: {packageName}',
				[
					'packageName' => $package['name'],
					'logCategory' => 'composition',
				]
			);

			return null;
		}

		$theme_data = $this->get_theme_data( $theme_folder );
		// This means we couldn't find the theme.
		if ( ! $theme_data ) {
			$this->logger->warning( 'Encountered WP theme "{packageName}" in composer.lock for which we couldn\'t extract the theme data ({theme_dir}).',
				[
					'packageName' => $package['name'],
					'theme_dir'   => $theme_folder,
					'logCategory' => 'composition',
				]
			);

			return null;
		}

		// The theme's stylesheet (folder).
		$stylesheet = array_keys( $theme_data )[0];
		$theme_data = reset( $theme_data );

		return [
			$stylesheet => [
				'name'         => $theme_data['Name'] ?? $package['name'],
				'stylesheet'   => $stylesheet,
				'template'     => $theme_data['Template'],
				'package-name' => $package['name'],
				'version'      => $package['version'],
				'description'  => $package['description'] ?? $theme_data['Description'],
				'homepage'     => $package['homepage'] ?? $theme_data['ThemeURI'],
				'authors'      => $package['authors'] ??
				                  ( ! empty( $theme_data['Author'] ) ?
					                  [
						                  [
							                  'name'     => $theme_data['Author'],
							                  'homepage' => $theme_data['AuthorURI'],
						                  ],
					                  ]
					                  :
					                  [] ),
				// If the template is different than stylesheet, we have a child-theme.
				'child-theme'  => $stylesheet !== $theme_data['Template'],
			],
		];
	}

	/**
	 * Get the theme data in a given theme folder.
	 *
	 * @since 0.1.0
	 *
	 * @see   \get_plugin_data()
	 *
	 * @param string $theme_folder The theme folder name.
	 *
	 * @return array[]|null null if the theme could not be found or a proper theme file (with headers) couldn't be identified.
	 *                      A single entry associative array on success. The key is the theme folder name and the data is the theme's header data.
	 */
	protected function get_theme_data( string $theme_folder ): ?array {
		$theme = \wp_get_theme( $theme_folder );
		if ( empty( $theme ) || ! $theme->exists() ) {
			return null;
		}

		return [
			$theme->get_stylesheet() => [
				'Name'        => $theme->get( 'Name' ),
				'ThemeURI'    => $theme->display( 'ThemeURI', true, false ),
				'Description' => $theme->display( 'Description', true, false ),
				'Author'      => $theme->display( 'Author', true, false ),
				'AuthorURI'   => $theme->display( 'AuthorURI', true, false ),
				'Version'     => $theme->get( 'Version' ),
				'Stylesheet'  => $theme->get_stylesheet(),
				'Template'    => $theme->get_template(),
				'Status'      => $theme->get( 'Status' ),
				'Tags'        => $theme->get( 'Tags' ),
				'Title'       => $theme->get( 'Name' ),
				'Parent'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : '',
			],
		];
	}
}
