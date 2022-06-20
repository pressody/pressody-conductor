<?php
/**
 * Assets provider.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\Provider;

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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function register_assets() {
		wp_register_script(
			'pressody_conductor-admin',
			$this->plugin->get_url( 'assets/js/admin.js' ),
			[ 'jquery' ],
			'20210628',
			true
		);

		wp_register_style(
			'pressody_conductor-admin',
			$this->plugin->get_url( 'assets/css/admin.css' ),
			[ 'wp-components' ],
			'20210628'
		);
	}

	/**
	 * Enqueue scripts and styles all over the admin.
	 *
	 * @since 0.11.0
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'pressody_conductor-admin' );
	}
}
