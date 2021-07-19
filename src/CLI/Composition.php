<?php
/**
 * Composition WP-CLI command.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\CLI;

use Composer\Semver\VersionParser;
use PixelgradeLT\Conductor\Composition\CompositionManager;
use PixelgradeLT\Conductor\Utils\StringHelpers;
use PixelgradeLT\Conductor\Utils\TimeHelpers;
use WP_CLI;
use WP_CLI\Formatter;
use \WP_CLI\Utils;
use function PixelgradeLT\Conductor\plugin;

/**
 * Monitor and manage the site's LT composition and its effects.
 *
 * ## EXAMPLES
 *
 *     # List the current site's composition info
 *     $ wp lt composition info
 *
 *     # Check the current composition.
 *     $ wp lt composition check
 *     Success: The site's composition (composer.json file) is OK!.
 *
 *     # Update the current composition
 *     $ wp lt composition update
 *     Success: The site's composition (composer.json file) is now UP-TO-DATE!
 *
 *     # Update the composition DB cache
 *     $ wp lt composition update-cache
 *     Success: The site's composition DB cache is now UP-TO-DATE!
 *
 *     # Clear the composition DB cache
 *     $ wp lt composition clear-cache
 *     Success: The site's composition DB cache has been CLEARED!
 *
 * @since   0.1.0
 * @package PixelgradeLT
 */
class Composition extends \WP_CLI_Command {

	/**
	 * Display details about the composition.
	 *
	 * ## OPTIONS
	 *
	 * [--plugin]
	 * : Specify a plugin name, file path (relative to the plugins directory), Composer package name or partial match of these (e.g. `part_*`) to output info about. Use quotes for complex strings.
	 *
	 * [--theme]
	 * : Specify a theme stylesheet (theme directory name) or Composer package name to output info about.
	 *
	 * [--last-updated]
	 * : The date and time the composition (composer.json file) was updated.
	 *
	 * [--lt-version]
	 * : The composition's LT version.
	 *
	 * [--lt-required-packages]
	 * : The composition's LT required packages.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Best to use it only when targeting a specific piece of information, not all the info.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--verbose]
	 * : Output more info.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition info
	 *      - List all the composition info.
	 *
	 *  2. wp lt composition info --verbose
	 *      - List all the composition info with even more details.
	 *
	 *  3. wp lt composition info --plugin=part_another-test/plugin.php
	 *      - List details for a plugin by providing its file path (relative to the plugins directory).
	 *
	 *  4. wp lt composition info --plugin="pixelgradelt-records/*"
	 *      - List all plugins that have the `pixelgradelt-records` vendor in their package name.
	 *
	 *  5. wp lt composition info --plugin="*" --verbose
	 *      - List all plugins with all their details.
	 *
	 *  6. wp lt composition info --theme="*"
	 *      - List all themes.
	 *
	 *  7. wp lt composition info --theme="Hi*"
	 *      - List all themes that begin with `hi`.
	 *
	 *  8. wp lt composition info --last-updated --format=json --verbose
	 *      - Displays JSON data with multiple versions of the composition's last updated datetime.
	 *
	 * @subcommand info
	 *
	 * @since      0.1.0
	 */
	public function info( $args, $assoc_args ) {
		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$verbose = Utils\get_flag_value( $assoc_args, 'verbose', false );
		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Read the current contents of the site's composer.json (the composition).
		if ( ! $composerJson = $compositionManager->get_composer_json( $verbose ) ) {
			WP_CLI::error( 'Could not read the site\'s composer.json file contents.' );

			return;
		}

		$plugins = $compositionManager->get_composition_plugin();
		// Default fields.
		$plugin_fields = [
			'name',
			'plugin-file',
			'package-name',
			'version',
		];
		$themes        = $compositionManager->get_composition_theme();
		// Default fields.
		$theme_fields = [
			'name',
			'stylesheet',
			'package-name',
			'version',
			'child-theme',
		];

		/**
		 * First, handle specific pieces of information if we received such instructions.
		 */

		if ( Utils\get_flag_value( $assoc_args, 'last-updated', false ) ) {
			if ( empty( $composerJson['time'] ) || ! $time = date( 'Y-m-d H:i:s', strtotime( $composerJson['time'] ) ) ) {
				WP_CLI::error( 'Missing or invalid composer.json "time" entry.' );

				return;
			}

			$humanReadable = TimeHelpers::time_elapsed_string( $composerJson['time'], $verbose );

			// For the 'table' format we will just display a sentence.
			if ( in_array( $format, [ 'json', 'yaml', 'csv' ] ) ) {
				$timeData = [
					'humanReadable' => $humanReadable,
					'dateTime'      => $time,
				];
				if ( $verbose ) {
					$timeData += [
						'dateTimeC' => date( 'c', strtotime( $composerJson['time'] ) ),
						'timestamp' => strtotime( $composerJson['time'] ),
					];
				}
				$fields     = array_intersect( [
					'humanReadable',
					'dateTime',
					'dateTimeC',
					'timestamp',
				], array_keys( $timeData ) );
				$assoc_args = compact( 'format', 'fields' );
				$formatter  = new Formatter( $assoc_args );
				$formatter->display_item( $timeData );

				return;
			}

			WP_CLI::log( 'The composition (composer.json file) was last updated ' . WP_CLI::colorize( "%B" . $humanReadable . "%n" ) . ' (' . $time . ')' );

			return;
		}

		if ( Utils\get_flag_value( $assoc_args, 'lt-version', false ) ) {
			if ( empty( $composerJson['extra']['lt-version'] ) ) {
				WP_CLI::error( 'Missing or invalid composer.json "lt-version" entry.' );

				return;
			}

			// For the 'table' format we will just display a sentence.
			if ( in_array( $format, [ 'json', 'yaml', 'csv' ] ) ) {
				$ltVersionData = [
					'ltVersion' => $composerJson['extra']['lt-version'],
				];
				if ( $verbose ) {
					$parser        = new VersionParser();
					$ltVersionData += [
						'ltVersionNormalized' => $parser->normalize( $composerJson['extra']['lt-version'] ),
					];
				}
				$fields     = array_intersect( [ 'ltVersion', 'ltVersionNormalized', ], array_keys( $ltVersionData ) );
				$assoc_args = compact( 'format', 'fields' );
				$formatter  = new Formatter( $assoc_args );
				$formatter->display_item( $ltVersionData );

				return;
			}

			WP_CLI::log( 'The composition (composer.json file) is at LT version ' . WP_CLI::colorize( "%Bv" . $composerJson['extra']['lt-version'] . "%n" ) );

			return;
		}

		if ( Utils\get_flag_value( $assoc_args, 'lt-required-packages', false ) ) {
			if ( empty( $composerJson['extra']['lt-required-packages'] ) ) {
				WP_CLI::error( 'Missing or invalid composer.json "lt-required-packages" entry.' );

				return;
			}

			$ltRequiredPackagesData = array_values( $composerJson['extra']['lt-required-packages'] );
			$fields                 = [ 'name', 'version', 'requiredBy', ];
			Utils\format_items( $format, $ltRequiredPackagesData, $fields );

			return;
		}

		$plugin_search = Utils\get_flag_value( $assoc_args, 'plugin', false );
		if ( ! empty( $plugin_search ) ) {
			// Search for matching plugins.
			$found = array_filter( $plugins, function ( $plugin_data ) use ( $plugin_search ) {
				if (
					( ! empty( $plugin_data['name'] ) && StringHelpers::partial_match_string( $plugin_search, $plugin_data['name'] ) )
					|| ( ! empty( $plugin_data['plugin-file'] ) && StringHelpers::partial_match_string( $plugin_search, $plugin_data['plugin-file'] ) )
					|| ( ! empty( $plugin_data['package-name'] ) && StringHelpers::partial_match_string( $plugin_search, $plugin_data['package-name'] ) )
				) {
					return true;
				}

				return false;
			} );

			if ( empty( $found ) ) {
				WP_CLI::error( 'No matching plugin found.' );
			}


			// Make sure that the fields exist.
			$first_found = reset( $found );
			$fields      = array_intersect( $plugin_fields, array_keys( $first_found ) );
			if ( $verbose ) {
				// Show all fields.
				$fields = array_keys( $first_found );
			}

			Utils\format_items( $format, $found, $fields );
		}

		$theme_search = Utils\get_flag_value( $assoc_args, 'theme', false );
		if ( ! empty( $theme_search ) ) {
			// Search for matching themes.
			$found = array_filter( $themes, function ( $theme_data ) use ( $theme_search ) {
				if (
					( ! empty( $theme_data['name'] ) && StringHelpers::partial_match_string( $theme_search, $theme_data['name'] ) )
					|| ( ! empty( $theme_data['stylesheet'] ) && StringHelpers::partial_match_string( $theme_search, $theme_data['stylesheet'] ) )
					|| ( ! empty( $theme_data['package-name'] ) && StringHelpers::partial_match_string( $theme_search, $theme_data['package-name'] ) )
				) {
					return true;
				}

				return false;
			} );

			if ( empty( $found ) ) {
				WP_CLI::error( 'No matching plugin found.' );
			}

			// Make sure that the fields exist.
			$first_found = reset( $found );
			$fields      = array_intersect( $theme_fields, array_keys( $first_found ) );
			if ( $verbose ) {
				// Show all fields.
				$fields = array_keys( $first_found );
			}

			Utils\format_items( $format, $found, $fields );
		}

		/**
		 * Second, display all the info.
		 */

		/**
		 * The last-update time in human-readable format.
		 */
		if ( empty( $composerJson['time'] ) || ! $time = date( 'Y-m-d H:i:s', strtotime( $composerJson['time'] ) ) ) {
			WP_CLI::error( 'Missing or invalid composer.json "time" entry.' );

			return;
		}
		WP_CLI::log( 'The composition (composer.json file) was last updated ' . WP_CLI::colorize( "%B" . TimeHelpers::time_elapsed_string( $composerJson['time'], $verbose ) . "%n" ) . ' (' . $time . ')' );
		WP_CLI::log( '' );

		/**
		 * The LT version.
		 */
		if ( empty( $composerJson['extra']['lt-version'] ) ) {
			WP_CLI::error( 'Missing or invalid composer.json "lt-version" entry.' );

			return;
		}
		WP_CLI::log( 'The composition (composer.json file) is at LT version ' . WP_CLI::colorize( "%Bv" . $composerJson['extra']['lt-version'] . "%n" ) );
		WP_CLI::log( '' );

		/**
		 * The composition plugins.
		 */
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'The composition\'s PLUGINS' . "%n" ) );
		WP_CLI::log( '  All the plugins that the composition installs and updates.' );
		WP_CLI::log( '' );

		$first_plugin = reset( $plugins );
		$fields       = array_intersect( $plugin_fields, array_keys( $first_plugin ) );
		if ( $verbose ) {
			// Show all fields.
			$fields = array_keys( $first_plugin );
		}
		Utils\format_items( $format, $plugins, $fields );

		/**
		 * The composition themes.
		 */
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'The composition\'s THEMES' . "%n" ) );
		WP_CLI::log( '  All the themes that the composition installs and updates.' );
		WP_CLI::log( '' );

		$first_theme = reset( $themes );
		$fields      = array_intersect( $theme_fields, array_keys( $first_theme ) );
		if ( $verbose ) {
			// Show all fields.
			$fields = array_keys( $first_theme );
		}
		Utils\format_items( $format, $themes, $fields );

		if ( empty( $composerJson['extra']['lt-required-packages'] ) ) {
			WP_CLI::error( 'Missing or invalid composer.json "lt-required-packages" entry.' );

			return;
		}

		/**
		 * The composition required LT-packages that determined the installation of all the LT packages present.
		 *
		 * These are most likely LT Parts.
		 */
		if ( empty( $composerJson['extra']['lt-required-packages'] ) ) {
			WP_CLI::log( 'No information about the composition\'s required LT-packages.' );

			return;
		}
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'The composition\'s REQUIRED LT-PACKAGES' . "%n" ) );
		WP_CLI::log( '  The LT-Packages that determined the installation of ALL the LT packages present.' );
		WP_CLI::log( '  These are most likely LT Parts.' );
		WP_CLI::log( '' );
		$ltRequiredPackagesData = array_values( $composerJson['extra']['lt-required-packages'] );
		$fields                 = [ 'name', 'version', 'requiredBy', ];
		Utils\format_items( $format, $ltRequiredPackagesData, $fields );
	}

	/**
	 * Checks the current site composition stored in composer.json for validity and update availability.
	 *
	 * ## OPTIONS
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition check
	 *
	 * @subcommand check
	 *
	 * @since      0.1.0
	 */
	public function check( $args, $assoc_args ) {
		WP_CLI::log( '------------------------------------------' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to check the site\'s composer.json..' . "%n" ) );
		WP_CLI::log( '------------------------------------------' );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$result = $compositionManager->check_update( true, Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		WP_CLI::log( '' );
		WP_CLI::log( '------------------------------------------' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition (composer.json file) is OK!' );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' . "%n" ) );
		}
		WP_CLI::log( '------------------------------------------' );
	}

	/**
	 * Checks the current site composition stored in composer.json for validity and updates it if there is an update available.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Try to recreate the composition based on LT details stored in the composer.json file (if they are still present).
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition update
	 *      - This will check and possibly update the current site composition (composer.json contents) by sending it to LT Records.
	 *
	 * @subcommand update
	 *
	 * @since      0.1.0
	 */
	public function update( $args, $assoc_args ) {
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to check and possibly update the site\'s composer.json..' . "%n" ) );
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		if ( Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			WP_CLI::log( '--------------------------------------------------------------' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting the site\'s composer.json reinitialisation..' . "%n" ) );
			WP_CLI::log( '--------------------------------------------------------------' );
			WP_CLI::log( '' );

			// We will first reinitialise the composer.json contents since we we've been instructed to do so.
			$result = $compositionManager->reinitialise( Utils\get_flag_value( $assoc_args, 'verbose', false ) );

			if ( $result ) {
				WP_CLI::warning( 'The site\'s composition (composer.json file) has been reinitialised (due to the --force flag).' );
			} else {
				WP_CLI::error( 'The site\'s composition (composer.json file) could not be reinitialised! See above for further details.' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '--------------------------------------------------------------' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to attempt the update of the site\'s composer.json.' . "%n" ) );
			WP_CLI::log( '--------------------------------------------------------------' );
			WP_CLI::log( '' );
		}

		$result = $compositionManager->check_update( false, Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		WP_CLI::log( '' );
		WP_CLI::log( '--------------------------------------------------------------' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition (composer.json file) is now UP-TO-DATE!' );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' . "%n" ) );
		}
		WP_CLI::log( '--------------------------------------------------------------' );
	}

	/**
	 * Updates the internal (DB) cache of the composition's plugins and themes (from `composer.lock`).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Refresh the cache regardless of checks.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition update-cache
	 *      - This will read the composer.json and update the DB cache with the valid plugins and themes present.
	 *
	 * @subcommand update-cache
	 *
	 * @since      0.1.0
	 */
	public function update_cache( $args, $assoc_args ) {
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to update the composition\'s DB cache.' . "%n" ) );
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$result = $compositionManager->refresh_composition_db_cache( Utils\get_flag_value( $assoc_args, 'force', false ), Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		WP_CLI::log( '' );
		WP_CLI::log( '--------------------------------------------------------------' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition DB cache is now UP-TO-DATE!' );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Couldn\'t update site\'s composition DB cache! See above for further details.' . "%n" ) );
		}
		WP_CLI::log( '--------------------------------------------------------------' );
	}

	/**
	 * Clears the internal (DB) cache of the composition's plugins and themes (from `composer.lock`).
	 *
	 * ## OPTIONS
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition clear-cache
	 *      - This will delete the composition\'s DB cache.
	 *
	 * @subcommand clear-cache
	 *
	 * @since      0.1.0
	 */
	public function clear_cache( $args, $assoc_args ) {
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to clear the composition\'s DB cache.' . "%n" ) );
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$result = $compositionManager->clear_composition_db_cache( Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		WP_CLI::log( '' );
		WP_CLI::log( '--------------------------------------------------------------' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition DB cache has been CLEARED!' );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Couldn\'t clear site\'s composition DB cache! See above for further details.' . "%n" ) );
		}
		WP_CLI::log( '--------------------------------------------------------------' );
	}

	/**
	 * Activates the composition installed plugins and/or theme.
	 *
	 * It relies on the DB cache, so it might be useful to refresh the cache before running it with `wp lt composition update-cache`.
	 *
	 * ## OPTIONS
	 *
	 * [--plugins]
	 * : Activate only the composition's plugins.
	 *
	 * [--theme]
	 * : Activate only the composition's theme.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp lt composition activate
	 *      - This will activate all plugins and theme installed by the composition.
	 *
	 *  2. wp lt composition activate --plugins
	 *      - This will activate all plugins installed by the composition.
	 *
	 *  3. wp lt composition activate --theme
	 *      - This will activate a theme installed by the composition. If a child theme is found it will be activated over it\'s parent theme.
	 *
	 * @subcommand activate
	 *
	 * @since      0.8.0
	 */
	public function activate( $args, $assoc_args ) {
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to activate plugins/themes installed via the composition..' . "%n" ) );
		WP_CLI::log( '--------------------------------------------------------------' );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$result = true;
		if ( Utils\get_flag_value( $assoc_args, 'plugins', false ) || ! Utils\get_flag_value( $assoc_args, 'theme', false ) ) {
			$result = $result && $compositionManager->handle_composition_plugins_activation();
		}

		if ( Utils\get_flag_value( $assoc_args, 'theme', false ) || ! Utils\get_flag_value( $assoc_args, 'plugins', false ) ) {
			$result = $result && $compositionManager->handle_composition_themes_activation();
		}

		WP_CLI::log( '' );
		WP_CLI::log( '--------------------------------------------------------------' );
		if ( $result ) {
			WP_CLI::success( 'The activation was successful!' );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'There were errors during activation! See above for further details.' . "%n" ) );
		}
		WP_CLI::log( '--------------------------------------------------------------' );
	}
}
