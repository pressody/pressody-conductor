<?php
/**
 * Plugins screen provider.
 *
 * @since   0.11.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Capabilities;
use Pressody\Conductor\Composition\CompositionManager;
use function Pressody\Conductor\current_user_has_role;

/**
 * Plugins screen provider class.
 *
 * @since 0.11.0
 */
class Plugins extends AbstractHookProvider {

	/**
	 * The composition manager.
	 *
	 * @since 0.10.0
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Create the plugins screen.
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
		add_action( 'admin_head-plugins.php', [ $this, 'change_screen_meta' ] );

		add_filter( 'show_advanced_plugins', [ $this, 'maybe_hide_advanced_plugins' ], 1, 2 );


		add_filter( 'all_plugins', [ $this, 'alter_composition_plugins_data_for_list_table' ], 99, 1 );

		// Hook to the more specific filter since it comes last.
		$composition_plugins = $this->composition_manager->get_composition_plugin();
		if ( ! empty( $composition_plugins ) ) {
			foreach ( array_keys( $composition_plugins ) as $plugin_file ) {
				add_filter( "plugin_action_links_{$plugin_file}", [
					$this,
					'alter_actions_for_composition_plugins',
				], 99, 2 );
			}
		}

		add_filter( 'plugin_row_meta', [ $this, 'alter_meta_for_composition_plugins' ], 99, 2 );
		add_filter( 'plugin_auto_update_setting_html', [
			$this,
			'alter_auto_update_html_for_composition_plugins',
		], 99, 2 );
	}

	/**
	 * Change the screen's meta (the Help section) to better reflect the PD reality.
	 *
	 * @since 0.11.0
	 */
	public function change_screen_meta() {
		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return;
		}

		// Override the Troubleshooting tab content, if present.
		if ( !! $screen->get_help_tab( 'compatibility-problems' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'compatibility-problems',
					'title'   => __( 'Troubleshooting', 'pressody_conductor' ),
					'content' =>
						'<p>' . __( '<strong>Your site\'s PD Composition</strong> is <strong>constantly tested and updated</strong> by us, the crew at Pressody. You should not encounter issues with the various parts, but if you do, <strong>don\'t hesitate to reach us</strong> and we\'ll do our very best to keep your site in tip-top shape.' ) . '</p>' .
						'<p>' . __( '<strong>If you install additional plugins,</strong> most of the time they will play nicely with the core of WordPress and with other plugins. Sadly, sometimes, a plugin&#8217;s code will get in the way of another plugin, causing compatibility issues. If your site starts doing strange things, this may be the problem. <strong>Try deactivating all the extra plugins</strong> and re-activating them in various combinations until you isolate which one(s) caused the issue.' ) . '</p>' .
						'<p>' . sprintf(
						/* translators: %s: WP_PLUGIN_DIR constant value. */
							__( 'If something goes wrong with a plugin and you can&#8217;t use WordPress, delete or rename that file in the %s directory and it will be automatically deactivated.' ),
							'<code>' . WP_PLUGIN_DIR . '</code>'
						) . '</p>',
				)
			);
		}

		// Override the Auto-updates tab content, if present.
		if ( !! $screen->get_help_tab( 'plugins-themes-auto-updates' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'plugins-themes-auto-updates',
					'title'   => __( 'Auto-updates', 'pressody_conductor' ),
					'content' =>
						'<p>' . __( 'Plugins delivered and managed by <strong>your site\'s PD Composition</strong> are <strong>updated automatically,</strong> so you don\'t need to worry about them anymore.', 'pressody_conductor' ) . '</p>' .
						'<p>' . __( 'For plugins that you\'ve installed besides the PD Composition, auto-updates can be enabled or disabled for each of them. Plugins with auto-updates enabled will display the estimated date of the next auto-update.', 'pressody_conductor' ) . '</p>' .
						'<p>' . __( 'Auto-updates are only available for plugins recognized by WordPress.org, or that include a compatible update system.', 'pressody_conductor' ) . '</p>',
				)
			);
		}
	}

	/**
	 * Hide advanced plugins (must-use and drop-ins) from users.
	 *
	 * @since 0.11.0
	 *
	 * @param bool   $show Whether to show the advanced plugins for the specified
	 *                     plugin type. Default true.
	 * @param string $type The plugin type. Accepts 'mustuse', 'dropins'.
	 *
	 * @return bool
	 */
	public function maybe_hide_advanced_plugins( bool $show, string $type ): bool {
		if ( ! current_user_has_role( Capabilities::SUPPORT_ROLE ) ) {
			$show = false;

			if ( 'mustuse' === $type ) {
				// We need to empty this global since Bedrock Autoloader fills it despite the filter.
				$GLOBALS['plugins']['mustuse'] = [];
			}
		}

		return $show;
	}

	/**
	 * Alter composition plugins data for use in the list table.
	 *
	 * @since 0.11.0
	 *
	 * @param array $all_plugins An array of plugins to display in the list table.
	 *
	 * @return array
	 */
	public function alter_composition_plugins_data_for_list_table( array $all_plugins ): array {
		$composition_plugins       = $this->composition_manager->get_composition_plugin();
		$composition_plugins_files = array_keys( $composition_plugins );
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( ! in_array( $plugin_file, $composition_plugins_files ) ) {
				continue;
			}

			// We make composition plugins behave like plugins with auto-update forced so they are treated as such in the list.
			$all_plugins[ $plugin_file ]['update-supported']   = false;
			$all_plugins[ $plugin_file ]['auto-update-forced'] = true;
		}

		return $all_plugins;
	}

	/**
	 * Alter composition plugins actions.
	 *
	 * @since 0.11.0
	 *
	 * @param string[] $actions     An array of plugin action links. By default this can include 'activate',
	 *                              'deactivate', and 'delete'. With Multisite active this can also include
	 *                              'network_active' and 'network_only' items.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return string[]
	 */
	public function alter_actions_for_composition_plugins( array $actions, string $plugin_file ): array {
		// Bail if this is not a plugin part of the site composition.
		if ( ! $this->composition_manager->get_composition_plugin( $plugin_file ) ) {
			return $actions;
		}

		$lt_flag = '<span class="wp-ui-text-primary">Pressody Composition</span>';

		// Hide certain actions for regular users, not Pressody support.
		if ( ! current_user_has_role( Capabilities::SUPPORT_ROLE ) ) {
			unset( $actions['deactivate'] );
			unset( $actions['delete'] );

			// @todo Maybe determine a more specific URL according to the user linked to this site.
			$account_url = 'https://pixelgrade.com/my-account/';
			$lt_flag     = '<a href="' . esc_url( $account_url ) . '">Pressody Composition</a>';
		}

		array_unshift( $actions, $lt_flag );

		return $actions;
	}

	/**
	 * Alter composition plugins meta details (used under description).
	 *
	 * @since 0.11.0
	 *
	 * @param string[] $plugin_meta $plugin_meta An array of the plugin's metadata, including
	 *                              the version, author, author URI, and plugin URI.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return string[]
	 */
	public function alter_meta_for_composition_plugins( array $plugin_meta, string $plugin_file ): array {
		// Bail if this is not a plugin part of the site composition.
		if ( ! $this->composition_manager->get_composition_plugin( $plugin_file ) ) {
			return $plugin_meta;
		}

		// Remove everything but the first 2 entries (version and author).
		if ( ! empty( $plugin_meta ) && count( $plugin_meta ) > 2 ) {
			$plugin_meta = array_slice( $plugin_meta, 0, 2 );
		}


		return $plugin_meta;
	}

	/**
	 * Alter composition plugins auto-update html.
	 *
	 * @since 0.11.0
	 *
	 * @param string $html        The HTML of the plugin's auto-update column content, including
	 *                            toggle auto-update action links and time to next update.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return string
	 */
	public function alter_auto_update_html_for_composition_plugins( string $html, string $plugin_file ): string {
		// Bail if this is not a plugin part of the site composition.
		if ( ! $this->composition_manager->get_composition_plugin( $plugin_file ) ) {
			return $html;
		}

		// Since we don't allow auto-updates for composition plugins, make that transparent.
		$html = '<span class="label">PD auto-updates</span>';

		return $html;
	}
}
