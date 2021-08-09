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
		add_filter( 'allow_dev_auto_core_updates', [ $this, 'allow_dev_auto_updates_for_wp_core' ], 99, 1 );
		add_filter( 'allow_minor_auto_core_updates', [ $this, 'allow_minor_auto_updates_for_wp_core' ], 99, 1 );
		add_filter( 'allow_major_auto_core_updates', [ $this, 'allow_major_auto_updates_for_wp_core' ], 99, 1 );

		add_filter( 'auto_update_plugin', [ $this, 'disable_auto_updates_for_composition_plugins' ], 99, 2 );
		add_filter( 'auto_update_theme', [ $this, 'disable_auto_updates_for_composition_themes' ], 99, 2 );
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
}
