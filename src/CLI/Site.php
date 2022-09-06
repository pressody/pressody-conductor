<?php
/**
 * Site WP-CLI command.
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

namespace Pressody\Conductor\CLI;

use Pressody\Conductor\Cache\CacheDispatcher;
use Pressody\Conductor\Composition\CompositionManager;
use \WP_CLI;
use WP_CLI\Formatter;
use WP_CLI\Utils;
use function Pressody\Conductor\plugin;

/**
 * Monitor and manage the entire PD Site.
 *
 * ## EXAMPLES
 *
 *     # List the current site's info
 *     $ wp pd site info
 *
 *     # Check the current site.
 *     $ wp pd site check
 *     Success: ...
 *
 *     # Clear the site's cache.
 *     $ wp pd site clear-cache
 *     Success: The site's cache has been CLEARED!
 *
 * @since   0.9.0
 * @package Pressody
 */
class Site extends \WP_CLI_Command {

	/**
	 * Display details about the site.
	 *
	 * ## OPTIONS
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
	 *      - List all the site info.
	 *
	 *  2. wp pd composition info --verbose
	 *      - List all the site info with even more details.
	 *
	 * @subcommand info
	 *
	 * @since      0.9.0
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

		WP_CLI::success( 'Nothing right now..' );

		exit( 0 );
	}

	/**
	 * Checks the site status and health.
	 *
	 * ## OPTIONS
	 *
	 * [--verbose]
	 * : Output more info regarding issues encountered with the site.
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp pd site check
	 *
	 * @subcommand check
	 *
	 * @since      0.9.0
	 */
	public function check( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to check the site..' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CompositionManager $compositionManager */
			$compositionManager = plugin()->get_container()->get( 'cli.composition.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		WP_CLI::success( 'Nothing right now..' );

		exit( 0 );
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
	 *  1. wp pd site clear-cache
	 *      - This will clear the site's cache(s).
	 *
	 * @subcommand clear-cache
	 *
	 * @since      0.9.0
	 */
	public function clear_cache( $args, $assoc_args ) {
		WP_CLI::log( '---' );
		WP_CLI::log( WP_CLI::colorize( "%B" . 'Starting to clear the site\'s cache(s).' . "%n" ) );
		WP_CLI::log( '' );

		try {
			/** @var CacheDispatcher $cacheManager */
			$cacheManager = plugin()->get_container()->get( 'cli.cache.manager' );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'There was a FATAL error in getting the "cli.composition.manager" container provider.' );
		}

		$cacheManager->schedule_cache_clear();

		WP_CLI::log( '' );
		WP_CLI::log( '---' );
		WP_CLI::success( 'The site\'s cache(s) have been scheduled to be CLEARED!' );
		exit( 0 );
	}
}
