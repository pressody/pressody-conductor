<?php
/**
 * WordPress updates adjustments provider.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.11.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Conductor\Composition\CompositionManager;

/**
 * Class to adjust WordPress updates (core, plugins, themes).
 *
 * @since 0.11.0
 */
class WPUpdates extends AbstractHookProvider {

	/**
	 * The composition manager.
	 *
	 * @since 0.10.0
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Create the WPUpdates adjustments provider.
	 *
	 * @param CompositionManager $composition_manager The composition manager.
	 */
	public function __construct(
		CompositionManager $composition_manager
	) {
		$this->composition_manager = $composition_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.11.0
	 */
	public function register_hooks() {
		/**
		 * Hooks for updates checks handling.
		 */
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'handle_plugins_update_check_transient' ], 99, 1 );
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'handle_themes_update_check_transient' ], 99, 1 );

		/**
		 * Hooks for auto-update handling.
		 */
		add_filter( 'allow_dev_auto_core_updates', [ $this, 'allow_dev_auto_updates_for_wp_core' ], 99, 1 );
		add_filter( 'allow_minor_auto_core_updates', [ $this, 'allow_minor_auto_updates_for_wp_core' ], 99, 1 );
		add_filter( 'allow_major_auto_core_updates', [ $this, 'allow_major_auto_updates_for_wp_core' ], 99, 1 );

		add_filter( 'auto_update_plugin', [ $this, 'disable_auto_updates_for_composition_plugins' ], 99, 2 );
		add_filter( 'auto_update_theme', [ $this, 'disable_auto_updates_for_composition_themes' ], 99, 2 );

		/**
		 * Hooks for preventing overwriting current files via 'Add new' plugin or theme.
		 */
		add_filter( 'upgrader_install_package_result', [ $this, 'block_composition_package_manual_install_overwrite' ], 99, 2 );
	}

	/**
	 * Filters the value of the `update_plugins` site transient before it is set.
	 *
	 * Do not static-type since others might push different value types.
	 *
	 * @since 0.11.0
	 *
	 * @param object $updates The new value of the transient.
	 *
	 * @return object
	 */
	public function handle_plugins_update_check_transient( $updates ) {
		if ( empty( $updates->no_update ) ) {
			$updates->no_update = [];
		}
		if ( empty( $updates->checked ) ) {
			$updates->checked = [];
		}

		// Get all composition plugins and add them as checked and with no update.
		$composition_plugins = $this->composition_manager->get_composition_plugin();
		foreach ( $composition_plugins as $plugin_file => $plugin_data ) {
			// Mark as checked.
			$updates->checked[ $plugin_file ] = $plugin_data['version'];

			// If there is an update response for this plugin, move the data to `no_update`.
			if ( isset( $updates->response[ $plugin_file ] ) ) {
				$updates->no_update[ $plugin_file ] = $updates->response[ $plugin_file ];

				// Make sure that the version is the current one.
				// And no download package, just to be sure.
				if ( is_array( $updates->no_update[ $plugin_file ] ) ) {
					$updates->no_update[ $plugin_file ]['new_version'] = $plugin_data['version'];
					$updates->no_update[ $plugin_file ]['package'] = '';
				} else {
					$updates->no_update[ $plugin_file ]->new_version = $plugin_data['version'];
					$updates->no_update[ $plugin_file ]->package = '';
				}

				unset( $updates->response[ $plugin_file ] );
			}
		}

		return $updates;
	}

	/**
	 * Filters the value of the `update_themes` site transient before it is set.
	 *
	 * Do not static-type since others might push different value types.
	 *
	 * @since 0.11.0
	 *
	 * @param object $updates The new value of the transient.
	 *
	 * @return object
	 */
	public function handle_themes_update_check_transient( $updates ) {
		if ( empty( $updates->no_update ) ) {
			$updates->no_update = [];
		}
		if ( empty( $updates->checked ) ) {
			$updates->checked = [];
		}

		// Get all composition themes and add them as checked and with no update.
		$composition_themes = $this->composition_manager->get_composition_theme();
		foreach ( $composition_themes as $theme_slug => $theme_data ) {
			// Mark as checked.
			$updates->checked[ $theme_slug ] = $theme_data['version'];

			// If there is an update response for this theme, move the data to `no_update`.
			if ( isset( $updates->response[ $theme_slug ] ) ) {
				$updates->no_update[ $theme_slug ] = $updates->response[ $theme_slug ];

				// Make sure that the version is the current one.
				// And no download package, just to be sure.
				if ( is_array( $updates->no_update[ $theme_slug ] ) ) {
					$updates->no_update[ $theme_slug ]['new_version'] = $theme_data['version'];
					$updates->no_update[ $theme_slug ]['package'] = '';
				} else {
					$updates->no_update[ $theme_slug ]->new_version = $theme_data['version'];
					$updates->no_update[ $theme_slug ]->package = '';
				}

				unset( $updates->response[ $theme_slug ] );
			}
		}

		return $updates;
	}

	/**
	 * Filters whether to enable automatic core updates for development versions.
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param bool $upgrade_dev Whether to enable automatic updates for development versions.
	 *
	 * @return bool Whether to enable automatic updates for development versions.
	 */
	public function allow_dev_auto_updates_for_wp_core( $upgrade_dev ) {
		// No need to allow dev auto updates right now.
		$upgrade_dev = false;

		return $upgrade_dev;
	}

	/**
	 * Filters whether to enable minor automatic core updates.
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param bool $upgrade_minor Whether to enable minor automatic core updates.
	 *
	 * @return bool Whether to enable minor automatic core updates.
	 */
	public function allow_minor_auto_updates_for_wp_core( $upgrade_minor ) {
		// Allow minor auto-updates for security purposes (updates with patches).
		// Normally minor updates would be delivered through the composition, but it's best to have some backup.
		$upgrade_minor = true;

		return $upgrade_minor;
	}

	/**
	 * Filters whether to enable major automatic core updates.
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param bool $upgrade_major Whether to enable major automatic core updates.
	 *
	 * @return bool Whether to enable major automatic core updates.
	 */
	public function allow_major_auto_updates_for_wp_core( $upgrade_major ) {
		// Do not allow major auto-updates since we manage that through the composition.
		$upgrade_major = false;

		return $upgrade_major;
	}

	/**
	 * Disable auto-updates for composition plugins since we manage the updates.
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param bool|null $update Whether to update. The value of null is internally used
	 *                          to detect whether nothing has hooked into this filter.
	 * @param object    $item   The update offer.
	 *
	 * @return bool|null Whether to automatically update a plugin.
	 */
	public function disable_auto_updates_for_composition_plugins( $update, $item ) {
		$screen = get_current_screen();
		// We will not mess with the auto-updates in the `wp-admin/plugins.php` section
		// so that the composition plugins will show as being auto-updated.
		if ( empty( $screen ) || empty( $screen->id ) || 'plugins' === $screen->id ) {
			return $update;
		}
		if ( ! empty( $item->plugin ) && !! $this->composition_manager->get_composition_plugin( $item->plugin ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Disable auto-updates for composition themes since we manage the updates.
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param bool|null $update Whether to update. The value of null is internally used
	 *                          to detect whether nothing has hooked into this filter.
	 * @param object    $item   The update offer.
	 *
	 * @return bool|null Whether to automatically update a plugin.
	 */
	public function disable_auto_updates_for_composition_themes( $update, $item ) {
		if ( ! empty( $item->theme ) && !! $this->composition_manager->get_composition_theme( $item->theme ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Filters the result of WP_Upgrader::install_package().
	 *
	 * Do not static-type since others might push different values that could be truthy or falsy.
	 *
	 * @since 0.11.0
	 *
	 * @param array|\WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array          $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return array|\WP_Error Whether to automatically update a plugin.
	 */
	public function block_composition_package_manual_install_overwrite( $result, $hook_extra ) {
		// We are only interested in situations when the package folder already exist.
		if ( ! is_wp_error( $result )
		     || 'folder_exists' !== $result->get_error_code()
		     || empty( $result->get_error_data( 'folder_exists' ) ) ) {

			return $result;
		}

		// Now, based on the limited information we get, determine if this is a composition package (plugin or theme).
		// We can only use the folder absolute path.
		$folder_path = $result->get_error_data( 'folder_exists' );
		$folder_name = basename( $folder_path );
		if ( false !== strpos( $folder_path, \path_join( WP_CONTENT_DIR, 'themes' ) ) ) {
			$composition_packages = $this->composition_manager->get_composition_theme();
		} else if ( false !== strpos( $folder_path, WP_PLUGIN_DIR ) ) {
			$composition_packages = $this->composition_manager->get_composition_plugin();
		} else {
			return $result;
		}

		foreach ( $composition_packages as $package_data ) {
			// We rely on the fact that Composer will install each package in a folder with the same name as the package name (without the vendor).
			$package_folder_name = basename( $package_data['package-name'] );
			if ( $folder_name === $package_folder_name ) {
				/* translators: 1: plugin or theme */
				$message = sprintf(
					wp_kses_post( __( 'You are trying to install a %1$s that <strong>is part of your LT Composition.</strong><br><strong>This is not allowed</strong> as the %1$s\'s installation and updates are handled <strong>automatically,</strong> as promised.<br>Please reach us at Pixelgrade if you have questions or need a helping hand.', 'pixelgrade_conductor' ) ),
					isset( $package_data['plugin-file'] ) ? 'plugin' : 'theme'
				);
				$message = '<p class="lt-inline-notice lt-notice-error lt-wrap">' . $message . '</p>';
				return new \WP_Error( 'composition_package', $message );
			}
		}

		return $result;
	}
}
