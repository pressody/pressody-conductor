<?php
/**
 * Settings screen provider.
 *
 * @since   0.1.0
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

namespace Pressody\Conductor\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Capabilities;

use function Pressody\Conductor\get_setting;

/**
 * Settings screen provider class.
 *
 * @since 0.1.0
 */
class Settings extends AbstractHookProvider {

	/**
	 * Create the setting screen.
	 */
	public function __construct() {
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ $this, 'add_menu_item' ] );
		} else {
			add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'add_sections' ] );
		add_action( 'admin_init', [ $this, 'add_settings' ] );
	}

	/**
	 * Add the settings menu item.
	 *
	 * @since 0.1.0
	 */
	public function add_menu_item() {
		$parent_slug = 'options-general.php';
		if ( is_network_admin() ) {
			$parent_slug = 'settings.php';
		}

		$page_hook = add_submenu_page(
				$parent_slug,
				esc_html__( 'Pressody Conductor', 'pressody_conductor' ),
				esc_html__( 'PD Conductor', 'pressody_conductor' ),
				Capabilities::MANAGE_OPTIONS,
				'pressody_conductor',
				[ $this, 'render_screen' ]
		);

		add_action( 'load-' . $page_hook, [ $this, 'load_screen' ] );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pressody_conductor-admin' );
		wp_enqueue_style( 'pressody_conductor-admin' );
	}

	/**
	 * Register settings.
	 *
	 * @since 0.1.0
	 */
	public function register_settings() {
		register_setting( 'pressody_conductor', 'pressody_conductor', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Add settings sections.
	 *
	 * @since 0.1.0
	 */
	public function add_sections() {
		add_settings_section(
				'default',
				esc_html__( 'General', 'pressody_conductor' ),
				[ $this, 'render_general_settings_explainer' ],
				'pressody_conductor'
		);
	}

	/**
	 * Register individual settings.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {

	}

	/**
	 * Sanitize settings.
	 *
	 * @since 0.1.0
	 *
	 * @param array $value Settings values.
	 *
	 * @return array Sanitized and filtered settings values.
	 */
	public function sanitize_settings( array $value ): array {

		return (array) apply_filters( 'pressody_conductor/sanitize_settings', $value );
	}

	/**
	 * Display the screen.
	 *
	 * @since 0.1.0
	 */
	public function render_screen() {

		$tabs = [
				'settings'      => [
						'name'       => esc_html__( 'Settings', 'pressody_conductor' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
				'system-status' => [
						'name'       => esc_html__( 'System Status', 'pressody_conductor' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
		];

		// By default, the Repository tabs is active.
		$active_tab = 'settings';

		include $this->plugin->get_path( 'views/screen-settings.php' );
	}

	/**
	 * Display a some explanation for the General settings.
	 *
	 * @since 0.1.0
	 */
	public function render_general_settings_explainer() {
		?>
		<div class="pressody_conductor-card">
			<p>
				<?php echo esc_html__( 'None right now.', 'pressody_conductor' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Retrieve a setting.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Optional. Default setting value.
	 *
	 * @return mixed
	 */
	protected function get_setting( string $key, $default = null ) {
		return get_setting( $key, $default );
	}
}
