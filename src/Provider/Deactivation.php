<?php
/**
 * Plugin deactivation routines.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Class to deactivate the plugin.
 *
 * @since 0.1.0
 */
class Deactivation extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		register_deactivation_hook( $this->plugin->get_file(), [ $this, 'deactivate' ] );
	}

	/**
	 * Deactivation routine.
	 *
	 * Deleting the rewrite rules option should force WordPress to regenerate
	 * them next time they're needed.
	 *
	 * @since 0.1.0
	 */
	public function deactivate() {
		delete_option( 'rewrite_rules' );
		delete_option( 'pixelgradelt_conductor_flush_rewrite_rules' );
	}
}
