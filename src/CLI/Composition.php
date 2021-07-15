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

use PixelgradeLT\Conductor\Composition\CompositionManager;
use \WP_CLI\Utils;
use function PixelgradeLT\Conductor\plugin;

/**
 * Manage the site's LT composition and its effects.
 *
 * ## EXAMPLES
 *
 *     # Generate a new plugin with unit tests
 *     $ wp scaffold plugin sample-plugin
 *     Success: Created plugin files.
 *     Success: Created test files.
 *
 *     # Generate theme based on _s
 *     $ wp scaffold _s sample-theme --theme_name="Sample Theme" --author="John Doe"
 *     Success: Created theme 'Sample Theme'.
 *
 *     # Generate code for post type registration in given theme
 *     $ wp scaffold post-type movie --label=Movie --theme=simple-life
 *     Success: Created /var/www/example.com/public_html/wp-content/themes/simple-life/post-types/movie.php
 *
 * @since   0.1.0
 * @package PixelgradeLT
 */
class Composition extends \WP_CLI_Command {

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
		\WP_CLI::log( '------------------------------------------' );
		\WP_CLI::log( 'Starting to check the site\'s composer.json..' );
		\WP_CLI::log( '------------------------------------------' );
		\WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			\WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		$result = $compositionManager->check_update( true, Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( '------------------------------------------' );
		if ( $result ) {
			\WP_CLI::success( 'The site\'s composition (composer.json file) is OK!' );
		} else {
			\WP_CLI::log( 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' );
		}
		\WP_CLI::log( '------------------------------------------' );
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
		\WP_CLI::log( '--------------------------------------------------------------' );
		\WP_CLI::log( 'Starting to check and possibly update the site\'s composer.json..' );
		\WP_CLI::log( '--------------------------------------------------------------' );
		\WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			\WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );

			return;
		}

		if ( Utils\get_flag_value( $assoc_args, 'force', false ) ) {
			// We will first reinitialise the composer.json contents.
			$result = $compositionManager->reinitialise( Utils\get_flag_value( $assoc_args, 'verbose', false ) );

			\WP_CLI::log( '' );
			\WP_CLI::log( '--------------------------------------------------------------' );
			if ( $result ) {
				\WP_CLI::warning( 'The site\'s composition (composer.json file) has been reinitialised (due to the --force flag).' );
			} else {
				\WP_CLI::error( 'The site\'s composition (composer.json file) could not be reinitialised! See above for further details.' );
			}
			\WP_CLI::log( '--------------------------------------------------------------' );
			\WP_CLI::log( '' );
			\WP_CLI::log( '--------------------------------------------------------------' );
			\WP_CLI::log( 'Starting to attempt to update the site\'s composer.json..' );
			\WP_CLI::log( '--------------------------------------------------------------' );
			\WP_CLI::log( '' );
		}

		$result = $compositionManager->check_update( false, Utils\get_flag_value( $assoc_args, 'verbose', false ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( '--------------------------------------------------------------' );
		if ( $result ) {
			\WP_CLI::success( 'The site\'s composition (composer.json file) is now UP-TO-DATE!' );
		} else {
			\WP_CLI::log( 'The site\'s composition (composer.json file) is NOT OK! See above for further details.' );
		}
		\WP_CLI::log( '--------------------------------------------------------------' );
	}

	protected function extract_args( $assoc_args, $defaults ) {
		$out = [];

		foreach ( $defaults as $key => $value ) {
			$out[ $key ] = Utils\get_flag_value( $assoc_args, $key, $value );
		}

		return $out;
	}
}
