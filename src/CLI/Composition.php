<?php
/**
 * Composition WP-CLI command.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\CLI;

use Composer\Semver\VersionParser;
use Pressody\Conductor\Composer\ComposerWrapper;
use Pressody\Conductor\Composition\CompositionManager;
use Pressody\Conductor\Utils\ArrayHelpers;
use Pressody\Conductor\Utils\StringHelpers;
use Pressody\Conductor\Utils\TimeHelpers;
use WP_CLI;
use WP_CLI\Formatter;
use \WP_CLI\Utils;
use function Pressody\Conductor\plugin;

/**
 * Monitor and manage the site's PD composition and its effects.
 *
 * ## EXAMPLES
 *
 *     # List the current site's composition info
 *     $ wp pd composition info
 *
 *     # Check the current composition.
 *     $ wp pd composition check
 *     Success: The site's composition (composer.json file) is OK!
 *
 *     # Update the current composition.
 *     $ wp pd composition update
 *     Success: The site's composition (composer.json file) is now UP-TO-DATE!
 *
 *     # Install/update/remove all the packages in the composition (composer.json file).
 *     $ wp pd composition composer-update
 *     Success: Successfully updated all the composition's packages!
 *
 *     # Update the composition DB cache.
 *     $ wp pd composition update-cache
 *     Success: The site's composition DB cache is now UP-TO-DATE!
 *
 *     # Clear the composition DB cache.
 *     $ wp pd composition clear-cache
 *     Success: The site's composition DB cache has been CLEARED!
 *
 *     # Active the plugins and/or theme installed by the composition.
 *     $ wp pd composition activate
 *     Success: The activation was successful!
 *
 *     # Run the composition update sequence of steps.
 *     $ wp pd composition update-sequence
 *     Success: The composition update sequence was successful!
 *
 *     # Reinitialize the composer.json with just the starter bare-bones (no PD Solutions).
 *     # Useful to use in a sequence (probably followed by `wp pd composition update`) for getting out of strange errors.
 *     $ wp pd composition reinit
 *     Success: The site's composition (composer.json file) has been reinitialised.
 *
 *
 * @since   0.1.0
 * @package Pressody
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
	 * [--pd-version]
	 * : The composition's PD version.
	 *
	 * [--pd-required-packages]
	 * : The composition's PD required packages.
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
	 *  1. wp pd composition info
	 *      - List all the composition info.
	 *
	 *  2. wp pd composition info --verbose
	 *      - List all the composition info with even more details.
	 *
	 *  3. wp pd composition info --plugin=part_another-test/plugin.php
	 *      - List details for a plugin by providing its file path (relative to the plugins directory).
	 *
	 *  4. wp pd composition info --plugin="pressody-records/*"
	 *      - List all plugins that have the `pressody-records` vendor in their package name.
	 *
	 *  5. wp pd composition info --plugin="*" --verbose
	 *      - List all plugins with all their details.
	 *
	 *  6. wp pd composition info --theme="*"
	 *      - List all themes.
	 *
	 *  7. wp pd composition info --theme="Hi*"
	 *      - List all themes that begin with `hi`.
	 *
	 *  8. wp pd composition info --last-updated --format=json --verbose
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
		}

		$verbose = Utils\get_flag_value( $assoc_args, 'verbose', false );
		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Read the current contents of the site's composer.json (the composition).
		if ( ! $composerJson = $compositionManager->get_composer_json( $verbose ) ) {
			WP_CLI::error( 'Could not read the site\'s composer.json file contents.' );
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

				exit( 0 );
			}

			WP_CLI::log( 'The composition (composer.json file) was last updated ' . WP_CLI::colorize( "%B" . $humanReadable . "%n" ) . ' (' . $time . ')' );

			exit( 0 );
		}

		if ( Utils\get_flag_value( $assoc_args, 'pd-version', false ) ) {
			if ( empty( $composerJson['extra']['pd-version'] ) ) {
				WP_CLI::error( 'Missing or invalid composer.json "pd-version" entry.' );
			}

			// For the 'table' format we will just display a sentence.
			if ( in_array( $format, [ 'json', 'yaml', 'csv' ] ) ) {
				$ltVersionData = [
					'pdVersion' => $composerJson['extra']['pd-version'],
				];
				if ( $verbose ) {
					$parser        = new VersionParser();
					$ltVersionData += [
						'pdVersionNormalized' => $parser->normalize( $composerJson['extra']['pd-version'] ),
					];
				}
				$fields     = array_intersect( [ 'pdVersion', 'pdVersionNormalized', ], array_keys( $ltVersionData ) );
				$assoc_args = compact( 'format', 'fields' );
				$formatter  = new Formatter( $assoc_args );
				$formatter->display_item( $ltVersionData );

				exit( 0 );
			}

			WP_CLI::log( 'The composition (composer.json file) is at PD version ' . WP_CLI::colorize( "%Bv" . $composerJson['extra']['pd-version'] . "%n" ) );

			exit( 0 );
		}

		if ( Utils\get_flag_value( $assoc_args, 'pd-required-packages', false ) ) {
			if ( empty( $composerJson['extra']['pd-required-packages'] ) ) {
				WP_CLI::error( 'Missing or invalid composer.json "pd-required-packages" entry.' );
			}

			$ltRequiredPackagesData = array_values( $composerJson['extra']['pd-required-packages'] );
			$fields                 = [ 'name', 'version', 'requiredBy', ];
			Utils\format_items( $format, $ltRequiredPackagesData, $fields );

			exit( 0 );
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
		}
		WP_CLI::log( 'The composition (composer.json file) was last updated ' . WP_CLI::colorize( "%B" . TimeHelpers::time_elapsed_string( $composerJson['time'], $verbose ) . "%n" ) . ' (' . $time . ')' );
		WP_CLI::log( '' );

		/**
		 * The PD version.
		 */
		if ( empty( $composerJson['extra']['pd-version'] ) ) {
			WP_CLI::error( 'Missing or invalid composer.json "pd-version" entry.' );
		}
		WP_CLI::log( 'The composition (composer.json file) is at PD version ' . WP_CLI::colorize( "%Bv" . $composerJson['extra']['pd-version'] . "%n" ) );
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

		if ( empty( $composerJson['extra']['pd-required-packages'] ) ) {
			WP_CLI::error( 'Missing or invalid composer.json "pd-required-packages" entry.' );
		}

		/**
		 * The composition required PD-packages that determined the installation of all the PD packages present.
		 *
		 * These are most likely PD Parts.
		 */
		if ( empty( $composerJson['extra']['pd-required-packages'] ) ) {
			WP_CLI::log( 'No information about the composition\'s required PD-packages.' );

			exit( 0 );
		}
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'The composition\'s REQUIRED PD-PACKAGES' . "%n" ) );
		WP_CLI::log( '  The PD-Packages that determined the installation of ALL the PD packages present.' );
		WP_CLI::log( '  These are most likely PD Parts.' );
		WP_CLI::log( '' );
		$ltRequiredPackagesData = array_values( $composerJson['extra']['pd-required-packages'] );
		$fields                 = [ 'name', 'version', 'requiredBy', ];
		Utils\format_items( $format, $ltRequiredPackagesData, $fields );

		exit( 0 );
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
	 *  1. wp pd composition check
	 *
	 * @subcommand check
	 *
	 * @since      0.1.0
	 */
	public function check( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to check the site\'s composer.json..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->check_update( true, Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition (composer.json file) is OK!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Checks the current site composition stored in composer.json for validity and updates it if there is an update available.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Try to recreate the composition based on PD details stored in the composer.json file (if they are still present).
	 *
	 * [--silent]
	 * : Do not trigger action hooks.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition update
	 *      - This will check and possibly update the current site composition (composer.json contents) by sending it to PD Records.
	 *
	 * @subcommand update
	 *
	 * @since      0.1.0
	 */
	public function update( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to check and possibly update the site\'s composer.json..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		if ( Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			WP_CLI::log( '---' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting the site\'s composer.json reinitialisation..' . "%n" ) );
			WP_CLI::log( '' );

			// We will first reinitialise the composer.json contents since we we've been instructed to do so.
			$result = $compositionManager->reinitialise(
				Utils\get_flag_value( $assoc_args, 'verbose', false ),
				Utils\get_flag_value( $assoc_args, 'silent', false )
			);

			if ( $result ) {
				WP_CLI::warning( 'The site\'s composition (composer.json file) has been reinitialised (due to the --force flag).' );
			} else {
				WP_CLI::error( 'The site\'s composition (composer.json file) could not be reinitialised! See above for further details.' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '---' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to attempt the update of the site\'s composer.json.' . "%n" ) );
			WP_CLI::log( '' );
		}

		$result = $compositionManager->check_update(
			false,
			Utils\get_flag_value( $assoc_args, 'verbose', false ),
			Utils\get_flag_value( $assoc_args, 'silent', false )
		);

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition (composer.json file) is now UP-TO-DATE!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Reinitialize the site composition (composer.json contents) with just the starter bare-bones, no PD Solutions.
	 *
	 * ## OPTIONS
	 *
	 * [--silent]
	 * : Do not trigger action hooks.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition reinitialization.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition reinit
	 *      - This will reinitialize the site composition (composer.json contents) with just the starter bare-bones, no PD Solutions.
	 *
	 * @subcommand reinit
	 *
	 * @since      0.9.0
	 */
	public function reinit( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Reinitializing the site\'s composer.json..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->reinitialise(
			Utils\get_flag_value( $assoc_args, 'verbose', false ),
			Utils\get_flag_value( $assoc_args, 'silent', false )
		);

		if ( $result ) {
			WP_CLI::success( 'The site\'s composition (composer.json file) has been reinitialised.' );
			exit( 0 );
		} else {
			WP_CLI::log( 'The site\'s composition (composer.json file) could not be reinitialised! See above for further details.' );
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Failed to reinitialize the site\'s composition (composer.json file)! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Backup the current composer.json.
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition backup
	 *      - Copy composer.json contents to a standard backup location for easy reverting.
	 *
	 * @subcommand backup
	 *
	 * @since      0.8.0
	 */
	public function backup( $args, $assoc_args ) {
		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->backup_composer_json();

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( false !== $result ) {
			WP_CLI::success( 'Backed-up the composition to "' . $result . '"' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Failed to backup the composition! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Revert composer.json to the backed-up contents (if the backup exists).
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition revert-backup
	 *      - Copy composer.json contents to a standard backup location for easy reverting.
	 *
	 * @subcommand revert-backup
	 *
	 * @since      0.8.0
	 */
	public function revert_backup( $args, $assoc_args ) {
		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->revert_composer_json();

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( false !== $result ) {
			WP_CLI::success( 'The composer.json file was reverted to it\'s backup.' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Failed to revert the composer.json file! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Installs all the packages in the composition at a specific moment (composer.lock). Useful for recreating/mirroring compositions.
	 *
	 * Uses a Composer wrapper to run the same logic as the CLI command `composer install`,
	 * meaning no changes are made to the composer.lock file even if there are changes in composer.json.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Do not actually make any package changes, only simulate.
	 *
	 * [--dev]
	 * : Install require-dev also.
	 *
	 * [--no-autoloader]
	 * : Skip autoloader generation.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition install.
	 *
	 * [--very-verbose]
	 * : Output detailed information on what the install process is doing.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition composer-install
	 *      - Almost the same as running composer install for the site composition, but better for our use-case.
	 *
	 * @subcommand composer-install
	 * @alias      cinstall
	 *
	 * @since      0.8.0
	 */
	public function composer_install( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Start installing packages from composer.lock..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$args = [];
		if ( Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			$args['dry-run'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'dev', false ) ) {
			$args['dev-mode'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'no-autoloader', false ) ) {
			$args['dump-autoloader']     = false;
			$args['optimize-autoloader'] = false;
		}
		if ( Utils\get_flag_value( $assoc_args, 'verbose', false ) ) {
			$args['verbose']         = true;
			$args['output-progress'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'very-verbose', false ) ) {
			$args['verbose']         = true;
			$args['output-progress'] = true;
			$args['debug']           = true;
		}
		$result = $compositionManager->composer_install( '', $args );

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'Successfully installed all the composition\'s locked packages!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Failed to install the composition\'s locked packages! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Updates all the packages currently in the composition (composer.json).
	 *
	 * Uses a Composer wrapper to run the same logic as the CLI command `composer update`,
	 * meaning existing packages get updated, removed packages get removed, and missing packages get installed.
	 *
	 * What is different from `composer update` is that, in case of error, we can revert the composer.json to a backed up version.
	 *
	 * ## OPTIONS
	 *
	 * [--revert]
	 * : Revert to the composer.json backup (if present) in case of errors.
	 *
	 * [--dry-run]
	 * : Do not actually make any package changes, only simulate.
	 *
	 * [--dev]
	 * : Install require-dev also.
	 *
	 * [--no-autoloader]
	 * : Skip autoloader generation.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition install.
	 *
	 * [--very-verbose]
	 * : Output detailed information on what the install process is doing.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition composer-update
	 *      - Almost the same as running composer update for the site composition, but better for our use-case.
	 *
	 * @subcommand composer-update
	 * @alias      cupdate
	 *
	 * @since      0.8.0
	 */
	public function composer_update( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Start updating packages from composer.json..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$args = [];
		if ( Utils\get_flag_value( $assoc_args, 'revert', false ) ) {
			$args['revert'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			$args['dry-run'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'dev', false ) ) {
			$args['dev-mode'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'no-autoloader', false ) ) {
			$args['dump-autoloader']     = false;
			$args['optimize-autoloader'] = false;
		}
		if ( Utils\get_flag_value( $assoc_args, 'verbose', false ) ) {
			$args['verbose']         = true;
			$args['output-progress'] = true;
		}
		if ( Utils\get_flag_value( $assoc_args, 'very-verbose', false ) ) {
			$args['verbose']         = true;
			$args['output-progress'] = true;
			$args['debug']           = true;
		}
		$result = $compositionManager->composer_update( '', $args );

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'Successfully updated all the composition\'s packages!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Failed to update the composition\'s packages! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Updates the internal (DB) cache of the composition's plugins and themes (from `composer.lock`).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Refresh the cache regardless of checks.
	 *
	 * [--silent]
	 * : Do not trigger action hooks.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition update-cache
	 *      - This will read the composer.json and update the DB cache with the valid plugins and themes present.
	 *
	 * @subcommand update-cache
	 *
	 * @since      0.1.0
	 */
	public function update_cache( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to update the composition\'s DB cache.' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->refresh_composition_db_cache(
			Utils\get_flag_value( $assoc_args, 'force', false ),
			Utils\get_flag_value( $assoc_args, 'verbose', false ),
			Utils\get_flag_value( $assoc_args, 'silent', false )
		);

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition DB cache is now UP-TO-DATE!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Couldn\'t update site\'s composition DB cache! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Clears the internal (DB) cache of the composition's plugins and themes (from `composer.lock`).
	 *
	 * ## OPTIONS
	 *
	 * [--silent]
	 * : Do not trigger action hooks.
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the composition update.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition clear-cache
	 *      - This will delete the composition\'s DB cache.
	 *
	 * @subcommand clear-cache
	 *
	 * @since      0.1.0
	 */
	public function clear_cache( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to clear the composition\'s DB cache.' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$result = $compositionManager->clear_composition_db_cache(
			Utils\get_flag_value( $assoc_args, 'verbose', false ),
			Utils\get_flag_value( $assoc_args, 'silent', false )
		);

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'The site\'s composition DB cache has been CLEARED!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'Couldn\'t clear site\'s composition DB cache! See above for further details.' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Activates the composition installed plugins and/or theme.
	 *
	 * It relies on the DB cache, so it might be useful to refresh the cache before running it with `wp pd composition update-cache`.
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
	 *  1. wp pd composition activate
	 *      - This will activate all plugins and theme installed by the composition.
	 *
	 *  2. wp pd composition activate --plugins
	 *      - This will activate all plugins installed by the composition.
	 *
	 *  3. wp pd composition activate --theme
	 *      - This will activate a theme installed by the composition. If a child theme is found it will be activated over it\'s parent theme.
	 *
	 * @subcommand activate
	 *
	 * @since      0.8.0
	 */
	public function activate( $args, $assoc_args ) {
		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$options = [
			'launch'     => true,  // Launch a new process, or reuse the existing.
			'exit_error' => false, // Exit on error by default.
			'return'     => 'all', // Capture and return output, or render in realtime.
			'parse'      => false, // Parse returned output as a particular format.
		];

		$result = true;
		/*
		 * For plugins, spawn new processes by running the WP-CLI `plugin activate` command
		 * instead of relying on CompositionManager::handle_composition_plugins_activation().
		 * This way we can reliably catch the occurrence of fatal errors during activation.
		 * WordPress "promises" a workflow for this (via plugin_sandbox_scrape()),
		 * but that is only imagined for user-triggered plugin activations via WP-Admin (it uses HTTP redirects to "catch" fatal errors).
		 */
		if ( Utils\get_flag_value( $assoc_args, 'plugins', false ) || ! Utils\get_flag_value( $assoc_args, 'theme', false ) ) {
			WP_CLI::log( '---' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Try to activate plugins installed via the composition..' . "%n" ) );
			WP_CLI::log( '' );

			$plugins = $compositionManager->get_composition_plugin();
			if ( ! empty( $plugins ) ) {
				foreach ( $plugins as $plugin_file => $plugin_data ) {
					if ( \is_plugin_active( $plugin_file ) ) {
						WP_CLI::log( 'Plugin already activate: ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] );
						continue;
					}

					$activation_result = WP_CLI::runcommand( 'plugin activate ' . $plugin_file, $options );
					if ( is_object( $activation_result ) ) {
						$activation_result = $this->objectToArrayRecursive( $activation_result );
					}
					if ( 0 === $activation_result['return_code'] ) {
						WP_CLI::log( 'Activated plugin: ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] );
					} else {
						$result = false;
						WP_CLI::warning( 'Failed to activate plugin: ' . $plugin_data['name'] . ' (' . $plugin_file . ') - v' . $plugin_data['version'] . '. Check the logs for more details.' );
						// We will silently deactivate it just to be sure.
						\deactivate_plugins( $plugin_file, true );
					}
				}
			}

			$invalid_plugins = \validate_active_plugins();
			if ( ! empty( $invalid_plugins ) ) {
				WP_CLI::log( 'The following active PLUGINS were found INVALID and deactivated:' );
				foreach ( $invalid_plugins as $plugin_file => $error ) {
					WP_CLI::log( '    - "' . $plugin_file . '" due to: ' . $error->get_error_message() );
				}
				WP_CLI::log( 'This might not be a reason to worry about since composition plugins that get removed from the composition will "suddenly disappear" and be identified as INVALID (missing).' );
			}
		}

		if ( Utils\get_flag_value( $assoc_args, 'theme', false ) || ! Utils\get_flag_value( $assoc_args, 'plugins', false ) ) {
			WP_CLI::log( '---' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Try to activate a theme installed via the composition..' . "%n" ) );
			WP_CLI::log( '' );

			$theme_activation_result = $compositionManager->handle_composition_themes_activation();
			$result                  = $result && $theme_activation_result;
		}

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		if ( $result ) {
			WP_CLI::success( 'The activation was successful!' );
			exit( 0 );
		} else {
			WP_CLI::log( WP_CLI::colorize( "%R" . 'There were errors during activation!' . "%n" ) );
		}

		exit( 1 );
	}

	/**
	 * Goes through all the steps to update the site's composition: update, composer-update, update-cache, activate.
	 *
	 * This is the go-to command for running via a cronjob.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Try to recreate the composition based on PD details stored in the composer.json file (if they are still present).
	 *
	 * [--revert]
	 * : Backup and revert the composer.json in case of errors.
	 *
	 * [--whisper]
	 * : Output minimal info about the progress.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd composition update-sequence
	 *      - This will go through all the steps to update the site's composition: update, composer-update, update-cache, activate.
	 *
	 * @subcommand update-sequence
	 *
	 * @since      0.8.0
	 */
	public function update_sequence( $args, $assoc_args ) {
		$whisper = Utils\get_flag_value( $assoc_args, 'whisper', false );

		if ( ! $whisper ) {
			WP_CLI::log( '---' );
			WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting the composition update sequence..' . "%n" ) );
			WP_CLI::log( '' );
		}

		$force  = Utils\get_flag_value( $assoc_args, 'force', false );
		$revert = Utils\get_flag_value( $assoc_args, 'revert', false );

		$options = array(
			// When reverting, catch output and return it in a structured format.
			// When not reverting but whispering, each command will return only the return_code (0 for success, anything else is an error).
			// Otherwise output live to shell.
			'return'     => $revert ? 'all' : ( $whisper ? 'return_code' : false ),
			// Launch new processes.
			'launch'     => true,
			// Halt entire command execution on error, but only if we weren't instructed to revert on failure.
			'exit_error' => ! $revert,
			// No parsing is needed.
			'parse'      => false,
		);

		$sequence_length = 4;

		$did_revert = false;

		/**
		 *  1. Run 'pd composition update'
		 */
		$current_command = 'pd composition update --silent' . ( $force ? ' --force' : '' );
		if ( ! $whisper ) {
			WP_CLI::log( '---' );
		}
		WP_CLI::log( '1/' . $sequence_length . ' - Running command `' . $current_command . '` ...' );
		if ( ! $whisper ) {
			WP_CLI::log( '' );
		}
		$result = WP_CLI::runcommand( $current_command, $options );
		if ( is_object( $result ) ) {
			$result = $this->objectToArrayRecursive( $result );
		}
		if ( $whisper && ( 0 === $result || ( $revert && 0 === $result['return_code'] ) ) ) {
			WP_CLI::log( '      Finished OK' );
		} else if ( $revert ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );

			if ( 0 !== $result['return_code'] ) {
				/**
				 * 1.1 Revert the composition and run the needed steps again.
				 */
				$current_command = 'pd composition revert-backup';
				if ( ! $whisper ) {
					WP_CLI::log( '---' );
				}
				WP_CLI::log( '1.1/' . $sequence_length . ' - Running command `' . $current_command . '` to revert the composition since we failed to check and update it...' );
				if ( ! $whisper ) {
					WP_CLI::log( '' );
				}
				$result = WP_CLI::runcommand( $current_command, $options );
				if ( is_object( $result ) ) {
					$result = $this->objectToArrayRecursive( $result );
				}
				if ( $whisper && 0 === $result['return_code'] ) {
					WP_CLI::log( '        Finished OK' );
				} else {
					// Output the sub-command output.
					WP_CLI::log( $result['stdout'] );
					// Output the sub-command errors.
					WP_CLI::log( $result['stderr'] );

					if ( 0 !== $result['return_code'] ) {
						// This is hopeless.
						exit( $result['return_code'] );
					}
				}
			}
		}

		/**
		 *  2. Run 'pd composition composer-update'
		 */
		$current_command = 'pd composition composer-update' . ( $revert ? ' --revert' : '' );
		if ( ! $whisper ) {
			WP_CLI::log( '---' );
		}
		WP_CLI::log( '2/' . $sequence_length . ' - Running command `' . $current_command . '` ...' );
		if ( ! $whisper ) {
			WP_CLI::log( '' );
		}
		$result = WP_CLI::runcommand( $current_command, $options );
		if ( is_object( $result ) ) {
			$result = $this->objectToArrayRecursive( $result );
		}
		if ( $whisper && ( 0 === $result || ( $revert && 0 === $result['return_code'] ) ) ) {
			WP_CLI::log( '      Finished OK' );
		} else if ( $revert && 0 !== $result['return_code'] ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );

			/**
			 * Composer update failed and probably the composition was reverted to the backup.
			 * 2.1 Run composer-update again, with the new composer.json contents.
			 */
			$current_command = 'pd composition composer-update';
			if ( ! $whisper ) {
				WP_CLI::log( '---' );
			}
			WP_CLI::log( '2.1/' . $sequence_length . ' - Running command `' . $current_command . '` again since composer.json was probably reverted...' );
			if ( ! $whisper ) {
				WP_CLI::log( '' );
			}
			$result = WP_CLI::runcommand( $current_command, $options );
			if ( is_object( $result ) ) {
				$result = $this->objectToArrayRecursive( $result );
			}
			if ( $whisper && 0 === $result['return_code'] ) {
				WP_CLI::log( '        Finished OK' );
			} else if ( 0 !== $result['return_code'] ) {
				// Output the sub-command errors.
				WP_CLI::log( $result['stderr'] );
			} else {
				// Output the output.
				WP_CLI::log( $result['stdout'] );
			}
			$did_revert = true;
		} else if ( $revert ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );
		}

		/**
		 *  3. Run 'pd composition update-cache --force'
		 */
		$current_command = 'pd composition update-cache --force --silent';
		if ( ! $whisper ) {
			WP_CLI::log( '---' );
		}
		WP_CLI::log( '3/' . $sequence_length . ' - Running command `' . $current_command . '` ...' );
		if ( ! $whisper ) {
			WP_CLI::log( '' );
		}
		$result = WP_CLI::runcommand( $current_command, $options );
		if ( is_object( $result ) ) {
			$result = $this->objectToArrayRecursive( $result );
		}
		if ( $whisper && ( 0 === $result || ( $revert && 0 === $result['return_code'] ) ) ) {
			WP_CLI::log( '      Finished OK' );
		} else if ( $revert ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );

			if ( 0 !== $result['return_code'] ) {
				exit( $result['return_code'] );
			}
		}

		/**
		 *  4. Run 'pd composition activate'
		 */
		$current_command = 'pd composition activate';
		if ( ! $whisper ) {
			WP_CLI::log( '---' );
		}
		WP_CLI::log( '4/' . $sequence_length . ' - Running command `' . $current_command . '` ...' );
		if ( ! $whisper ) {
			WP_CLI::log( '' );
		}
		$result = WP_CLI::runcommand( $current_command, $options );
		if ( is_object( $result ) ) {
			$result = $this->objectToArrayRecursive( $result );
		}
		if ( $whisper && ( 0 === $result || ( $revert && 0 === $result['return_code'] ) ) ) {
			WP_CLI::log( '      Finished OK' );
		} else if ( $revert && 0 !== $result['return_code'] && ! $did_revert ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );

			/**
			 * Failed to activate plugins and theme.
			 * 4.1 Revert the composition if it was not already reverted and run the needed steps again.
			 */
			$current_command = 'pd composition revert-backup';
			if ( ! $whisper ) {
				WP_CLI::log( '---' );
			}
			WP_CLI::log( '4.1/' . $sequence_length . ' - Running command `' . $current_command . '` to revert the composition since we failed to activate plugins and theme...' );
			if ( ! $whisper ) {
				WP_CLI::log( '' );
			}
			$result = WP_CLI::runcommand( $current_command, $options );
			if ( is_object( $result ) ) {
				$result = $this->objectToArrayRecursive( $result );
			}
			if ( $whisper && 0 === $result['return_code'] ) {
				WP_CLI::log( '        Finished OK' );
			} else {
				// Output the sub-command output.
				WP_CLI::log( $result['stdout'] );
				// Output the sub-command errors.
				WP_CLI::log( $result['stderr'] );

				if ( 0 !== $result['return_code'] ) {
					exit( $result['return_code'] );
				}
			}

			/**
			 *  4.2 Run 'pd composition composer-update'
			 */
			$current_command = 'pd composition composer-update' . ( $revert ? ' --revert' : '' );
			if ( ! $whisper ) {
				WP_CLI::log( '---' );
			}
			WP_CLI::log( '4.2/' . $sequence_length . ' - Running again command `' . $current_command . '` ...' );
			if ( ! $whisper ) {
				WP_CLI::log( '' );
			}
			$result = WP_CLI::runcommand( $current_command, $options );
			if ( is_object( $result ) ) {
				$result = $this->objectToArrayRecursive( $result );
			}
			if ( $whisper && 0 === $result['return_code'] ) {
				WP_CLI::log( '        Finished OK' );
			} else {
				// Output the sub-command output.
				WP_CLI::log( $result['stdout'] );
				// Output the sub-command errors.
				WP_CLI::log( $result['stderr'] );

				if ( 0 !== $result['return_code'] ) {
					exit( $result['return_code'] );
				}
			}

			/**
			 *  4.3 Run 'pd composition update-cache --force'
			 */
			$current_command = 'pd composition update-cache --force --silent';
			if ( ! $whisper ) {
				WP_CLI::log( '---' );
			}
			WP_CLI::log( '4.3/' . $sequence_length . ' - Running again command `' . $current_command . '` ...' );
			if ( ! $whisper ) {
				WP_CLI::log( '' );
			}
			$result = WP_CLI::runcommand( $current_command, $options );
			if ( is_object( $result ) ) {
				$result = $this->objectToArrayRecursive( $result );
			}
			if ( $whisper && 0 === $result['return_code'] ) {
				WP_CLI::log( '        Finished OK' );
			} else {
				// Output the sub-command output.
				WP_CLI::log( $result['stdout'] );
				// Output the sub-command errors.
				WP_CLI::log( $result['stderr'] );

				if ( 0 !== $result['return_code'] ) {
					exit( $result['return_code'] );
				}

			}

			/**
			 *  4.4 Run 'pd composition activate'
			 */
			$current_command = 'pd composition activate';
			if ( ! $whisper ) {
				WP_CLI::log( '---' );
			}
			WP_CLI::log( '4.4/' . $sequence_length . ' - Running again command `' . $current_command . '` ...' );
			if ( ! $whisper ) {
				WP_CLI::log( '' );
			}
			$result = WP_CLI::runcommand( $current_command, $options );
			if ( is_object( $result ) ) {
				$result = $this->objectToArrayRecursive( $result );
			}
			if ( $whisper && 0 === $result['return_code'] ) {
				WP_CLI::log( '        Finished OK' );
			} else {
				// Output the sub-command output.
				WP_CLI::log( $result['stdout'] );
				// Output the sub-command errors.
				WP_CLI::log( $result['stderr'] );
			}
		} else if ( $revert ) {
			// Output the sub-command output.
			WP_CLI::log( $result['stdout'] );
			// Output the sub-command errors.
			WP_CLI::log( $result['stderr'] );

			if ( 0 !== $result['return_code'] ) {
				exit( $result['return_code'] );
			}
		}

		// If we've made it this far, all is good since we have `exist_error` set to true.
		if ( ! $whisper ) {
			WP_CLI::log( '' );
			WP_CLI::log( '---' );
		}
		WP_CLI::success( 'The composition update sequence was successful!' );
	}

	/**
	 * Recursively cast an object to an associative array.
	 *
	 * @since 0.8.0
	 *
	 * @param object $object
	 *
	 * @return array
	 */
	protected function objectToArrayRecursive( object $object ): array {
		$json = json_encode( $object );
		if ( json_last_error() !== \JSON_ERROR_NONE ) {
			$message = esc_html__( 'Unable to encode schema array as JSON', 'pressody_records' );
			if ( function_exists( 'json_last_error_msg' ) ) {
				$message .= ': ' . json_last_error_msg();
			}
			WP_CLI::error( $message );
		}

		return (array) json_decode( $json, true );
	}
}
