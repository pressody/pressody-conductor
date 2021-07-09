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

use Cedaro\WP\Plugin\AbstractHookProvider;
use Composer\Json\JsonFile;
use PixelgradeLT\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\ParsingException;
use function PixelgradeLT\Conductor\is_debug_mode;
use function PixelgradeLT\Conductor\is_dev_url;
use WP_Http as HTTP;
use function PixelgradeLT\Conductor\is_plugin_file;

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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param QueueInterface  $queue  Queue.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		QueueInterface $queue,
		LoggerInterface $logger
	) {
		$this->queue  = $queue;
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_events' );

		$this->add_action( 'pixelgradelt_conductor/midnight', 'check_update' );
		$this->add_action( 'pixelgradelt_conductor/hourly', 'maybe_update_composition_plugins_and_themes' );
		//		$this->add_action( 'after_setup_theme', 'check_update' );
		$this->add_action( 'after_setup_theme', 'maybe_update_composition_plugins_and_themes' );
	}

	/**
	 * Maybe schedule the actions/events, if it is not already scheduled.
	 *
	 * @since 0.1.0
	 */
	protected function schedule_events() {
		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pixelgradelt_conductor/midnight', [], 'pixelgrade-conductor' );
		}

		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pixelgradelt_conductor/hourly', [], 'pixelgrade-conductor' );
		}
	}

	/**
	 * Check with LT Records if the current site composition should be updated.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	protected function check_update(): bool {
		if ( ! defined( 'LT_RECORDS_API_KEY' ) || empty( LT_RECORDS_API_KEY )
		     || ! defined( 'LT_RECORDS_API_PWD' ) || empty( LT_RECORDS_API_PWD )
		     || ! defined( 'LT_RECORDS_COMPOSITION_REFRESH_URL' ) || empty( LT_RECORDS_COMPOSITION_REFRESH_URL )
		) {
			$this->logger->warning( 'Could not check for composition update because there are missing or empty environment variables.' );

			return false;
		}

		// Read the current contents of the site's composer.json (the composition).
		$composerJsonFile = new JsonFile( \path_join( LT_ROOT_DIR, 'composer.json' ) );
		if ( ! $composerJsonFile->exists() ) {
			$this->logger->error( 'The site\'s composer.json file doesn\'t exist.' );

			return false;
		}
		try {
			$composerJsonCurrentContents = $composerJsonFile->read();
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be read: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		} catch ( ParsingException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be parsed: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
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
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= HTTP::BAD_REQUEST ) {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$accepted_keys = array_fill_keys( [ 'code', 'message', 'data' ], '' );
			$body          = array_replace( $accepted_keys, array_intersect_key( $body, $accepted_keys ) );
			$this->logger->error( 'The composition update check failed with code "{code}": {message}',
				[
					'code'    => $body['code'],
					'message' => $body['message'],
					'data'    => $body['data'],
				]
			);

			return false;
		}

		// If we have nothing to update, bail.
		if ( wp_remote_retrieve_response_code( $response ) === HTTP::NO_CONTENT ) {
			return false;
		}

		// We get back the entire composer.json contents.
		$receivedComposerJson = json_decode( wp_remote_retrieve_body( $response ), true );

		// Now we need to prepare the new contents and write them (if needed) the same way Composer does it.
		try {
			$composerJsonFile->write( $receivedComposerJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		} catch ( \Exception $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be written with the updated contents: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		}

		return true;
	}

	/**
	 * Check the `composer.lock` file for modifications and update the data we have about the included plugins and themes.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	protected function maybe_update_composition_plugins_and_themes(): bool {
		// Read the current contents of the site's composer.lock.
		$composerLockJsonFile = new JsonFile( \path_join( LT_ROOT_DIR, 'composer.lock' ) );
		if ( ! $composerLockJsonFile->exists() ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t exist.' );

			return false;
		}
		try {
			$composerLockJsonCurrentContents = $composerLockJsonFile->read();
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'The site\'s composer.lock file could not be read: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		} catch ( ParsingException $e ) {
			$this->logger->error( 'The site\'s composer.lock file could not be parsed: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		}

		if ( empty( $composerLockJsonCurrentContents['content-hash'] ) ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t have a "content-hash" entry.' );

			return false;
		}

		if ( empty( $composerLockJsonCurrentContents['packages'] ) ) {
			$this->logger->warning( 'The site\'s composer.lock file doesn\'t have any installed packages.' );

			return false;
		}

		// Check if the "content-hash" is different than what we have.
		// If they are the same, we don't need to update anything.
		if ( get_option( self::COMPOSER_LOCK_HASH_OPTION_NAME ) === $composerLockJsonCurrentContents['content-hash'] ) {
			return true;
		}

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
		$added_plugins = array_diff_key( $plugins, $old_plugins );
		$updated_plugins = array_intersect_key( $plugins, $old_plugins );
		foreach ( $updated_plugins as $key => $plugin_data ) {
			if ( \version_compare( $plugin_data['version'], $old_plugins[ $key ]['version'], '=' ) ) {
				unset ( $updated_plugins[ $key ] );
			}
		}

		if ( ! empty( $removed_plugins ) ) {
			$message = '[COMPOSITION UPDATE] The following PLUGINS have been REMOVED, according to composer.lock:' . PHP_EOL;
			foreach ( $removed_plugins as $plugin_file => $plugin_data ) {
				$message .=  '    - ' . $plugin_data['name'] . ' ('. $plugin_file . ') - v' . $plugin_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}
		if ( ! empty( $added_plugins ) ) {
			$message = '[COMPOSITION UPDATE] The following PLUGINS have been ADDED, according to composer.lock:' . PHP_EOL;
			foreach ( $added_plugins as $plugin_file => $plugin_data ) {
				$message .=  '    - ' . $plugin_data['name'] . ' ('. $plugin_file . ') - v' . $plugin_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}
		if ( ! empty( $updated_plugins ) ) {
			$message = '[COMPOSITION UPDATE] The following PLUGINS have been UPDATED, according to composer.lock:' . PHP_EOL;
			foreach ( $updated_plugins as $plugin_file => $plugin_data ) {
				$message .=  '    - ' . $plugin_data['name'] . ' ('. $plugin_file . ') - from version v' . $old_plugins[ $plugin_file ]['version'] . ' to version v'. $plugins[ $plugin_file ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}

		/**
		 * Log what has actually happened with themes
		 */
		$removed_themes = array_diff_key( $old_themes, $themes );
		$added_themes = array_diff_key( $themes, $old_themes );
		$updated_themes = array_intersect_key( $themes, $old_themes );
		foreach ( $updated_themes as $key => $plugin_data ) {
			if ( \version_compare( $plugin_data['version'], $old_themes[ $key ]['version'], '=' ) ) {
				unset ( $updated_themes[ $key ] );
			}
		}

		if ( ! empty( $removed_themes ) ) {
			$message = '[COMPOSITION UPDATE] The following THEMES have been REMOVED, according to composer.lock:' . PHP_EOL;
			foreach ( $removed_themes as $stylesheet => $theme_data ) {
				$message .=  '    - ' . $theme_data['name'] . ' ('. $stylesheet . ') - v' . $theme_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}
		if ( ! empty( $added_themes ) ) {
			$message = '[COMPOSITION UPDATE] The following THEMES have been ADDED, according to composer.lock:' . PHP_EOL;
			foreach ( $added_themes as $stylesheet => $theme_data ) {
				$message .=  '    - ' . $theme_data['name'] . ' ('. $stylesheet . ') - v' . $theme_data['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}
		if ( ! empty( $updated_themes ) ) {
			$message = '[COMPOSITION UPDATE] The following THEMES have been UPDATED, according to composer.lock:' . PHP_EOL;
			foreach ( $updated_themes as $stylesheet => $theme_data ) {
				$message .=  '    - ' . $theme_data['name'] . ' ('. $stylesheet . ') - from version v' . $old_themes[ $stylesheet ]['version'] . ' to version v'. $themes[ $stylesheet ]['version'] . PHP_EOL;
			}
			$this->logger->info( $message );
		}

		// Save the plugins and themes data.
		update_option( self::COMPOSITION_PLUGINS_OPTION_NAME, $plugins, true );
		update_option( self::COMPOSITION_THEMES_OPTION_NAME, $themes, true );

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
				'name'         => $plugin_data['Name'] ?? $package['name'],
				'plugin-file'  => $plugin_file,
				'package-name' => $package['name'],
				'version'      => $package['version'],
				'description'  => $package['description'] ?? $plugin_data['Description'],
				'homepage'     => $package['homepage'] ?? $plugin_data['PluginURI'],
				'authors'      => $package['authors'] ??
				                  ( ! empty( $plugin_data['Author'] ) ?
					                  [
						                  [
							                  'name'     => $plugin_data['Author'],
							                  'homepage' => $plugin_data['AuthorURI'],
						                  ],
					                  ]
					                  :
					                  [] ),
				'ltpart-plugin' => $is_lt_part_plugin
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
				]
			);

			return null;
		}

		$theme_data = $this->get_theme_data( $theme_folder );
		// This means we couldn't find the theme.
		if ( ! $theme_data ) {
			$this->logger->warning( 'Encountered WP theme "{packageName}" in composer.lock for which we couldn\'t extract the theme data.',
				[
					'packageName' => $package['name'],
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
				'ThemeURI'         => $theme->display( 'ThemeURI', true, false ),
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
