<?php
/**
 * Assets provider.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Assets provider class.
 *
 * @since 0.1.0
 */
class AdminAssets extends AbstractHookProvider {
	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function register_assets() {
		wp_register_script(
			'pixelgradelt_conductor-admin',
			$this->plugin->get_url( 'assets/js/admin.js' ),
			[ 'jquery' ],
			'20210628',
			true
		);

		wp_register_style(
			'pixelgradelt_conductor-admin',
			$this->plugin->get_url( 'assets/css/admin.css' ),
			[ 'wp-components' ],
			'20210628'
		);
	}
}
