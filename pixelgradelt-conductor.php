<?php
/**
 * PixelgradeLT Conductor
 *
 * @package PixelgradeLT
 * @author  Vlad Olaru <vlad@pixelgrade.com>
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: PixelgradeLT Conductor
 * Plugin URI: https://github.com/pixelgradelt/pixelgradelt-conductor
 * Description: Deliver a smooth, secure existence for a PixelgradeLT WP Site. This should be a MU plugin.
 * Version: 0.8.0
 * Author: Pixelgrade
 * Author URI: https://pixelgrade.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pixelgradelt_conductor
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Network: false
 * GitHub Plugin URI: pixelgradelt/pixelgradelt-conductor
 * Release Asset: true
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
const VERSION = '0.8.0';

// Load the Composer autoloader.
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

// Read environment variables from the $_ENV array also.
\Env\Env::$options |= \Env\Env::USE_ENV_ARRAY;

// Load the WordPress plugin administration API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Load the Action Scheduler directly since it does not use Composer autoload.
// @link https://github.com/woocommerce/action-scheduler/issues/471
require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

// Create a container and register a service provider.
$pixelgradelt_conductor_container = new Container();
$pixelgradelt_conductor_container->register( new ServiceProvider() );

// Initialize the plugin and inject the container.
$pixelgradelt_conductor = plugin()
	->set_basename( plugin_basename( __FILE__ ) )
	->set_directory( plugin_dir_path( __FILE__ ) )
	->set_file( __DIR__ . '/pixelgradelt-conductor.php' )
	->set_slug( 'pixelgradelt-conductor' )
	->set_url( plugin_dir_url( __FILE__ ) )
	->define_constants()
	->set_container( $pixelgradelt_conductor_container )
	->register_wp_cli_commands()
	->register_hooks( $pixelgradelt_conductor_container->get( 'hooks.activation' ) )
	->register_hooks( $pixelgradelt_conductor_container->get( 'hooks.deactivation' ) );

add_action( 'plugins_loaded', [ $pixelgradelt_conductor, 'compose' ], 5 );
