<?php
/**
 * Pressody Conductor
 *
 * @package Pressody
 * @author  Vlad Olaru <vladpotter85@gmail.com>
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Pressody Conductor
 * Plugin URI: https://github.com/pressody/pressody-conductor
 * Description: Deliver a smooth, secure existence for a Pressody WP Site. This should be a MU plugin.
 * Version: 0.12.0
 * Author: Pressody
 * Author URI: https://getpressody.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressody_conductor
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Network: false
 * GitHub Plugin URI: pressody/pressody-conductor
 * Release Asset: true
 */

declare ( strict_types=1 );

namespace Pressody\Conductor;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
const VERSION = '0.12.0';

// Load the Composer autoloader if the vendor packages are installed locally, not at the project level.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

// Display a notice and bail if dependencies are missing.
if ( ! function_exists( __NAMESPACE__ . '\autoloader_classmap' ) ) {
	require_once __DIR__ . '/src/functions.php';
	add_action( 'admin_notices', __NAMESPACE__ . '\display_missing_dependencies_notice' );

	return;
}

// Autoload mapped classes.
spl_autoload_register( __NAMESPACE__ . '\autoloader_classmap' );

/**
 * Plugin's vendor directory absolute path.
 *
 * @var string
 */
const VENDOR_DIR = __DIR__ . '/vendor';

// Read environment variables from the $_ENV array also.
\Env\Env::$options |= \Env\Env::USE_ENV_ARRAY;

// Load the WordPress plugin administration API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Load the Action Scheduler directly since it does not use Composer autoload.
// @link https://github.com/woocommerce/action-scheduler/issues/471
if ( file_exists( PD_ROOT_DIR . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once PD_ROOT_DIR . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
} else if ( file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

	// Create a container and register a service provider.
$pressody_conductor_container = new Container();
$pressody_conductor_container->register( new ServiceProvider() );

// Initialize the plugin and inject the container.
$pressody_conductor = plugin()
	->set_basename( plugin_basename( __FILE__ ) )
	->set_directory( plugin_dir_path( __FILE__ ) )
	->set_file( __DIR__ . '/pressody-conductor.php' )
	->set_slug( 'pressody-conductor' )
	->set_url( plugin_dir_url( __FILE__ ) )
	->define_constants()
	->set_container( $pressody_conductor_container )
	->register_wp_cli_commands()
	->register_hooks( $pressody_conductor_container->get( 'hooks.activation' ) )
	->register_hooks( $pressody_conductor_container->get( 'hooks.deactivation' ) );

add_action( 'plugins_loaded', [ $pressody_conductor, 'compose' ], 5 );
