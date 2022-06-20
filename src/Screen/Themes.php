<?php
/**
 * Themes screen provider.
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
use Pressody\Conductor\Utils\ArrayHelpers;
use function Pressody\Conductor\current_user_has_role;

/**
 * Themes screen provider class.
 *
 * @since 0.11.0
 */
class Themes extends AbstractHookProvider {

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
		add_action( 'admin_head-themes.php', [ $this, 'change_screen_meta' ] );

		add_filter( 'wp_prepare_themes_for_js', [ $this, 'alter_composition_themes_data_for_list_table' ], 99, 1 );

		add_filter( 'theme_auto_update_setting_template', [ $this, 'alter_theme_auto_update_setting_template' ], 99, 1 );
	}

	/**
	 * Change the screen's meta (the Help section) to better reflect the PD reality.
	 *
	 * @since 0.11.0
	 */
	public function change_screen_meta() {
		$screen = \get_current_screen();
		if ( empty( $screen ) ) {
			return;
		}

		// Override the Troubleshooting tab content, if present.
		if ( ! ! $screen->get_help_tab( 'adding-themes' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'adding-themes',
					'title'   => __( 'Adding Themes', 'pressody_conductor' ),
					'content' =>
						'<p>' . __( '<strong>Your site\'s PD Composition</strong> already comes with all the styling your need (a theme, plugins, etc.) Before deciding to add another theme to your site, <strong>please reach us out at Pressody</strong> to see if we can help you make the most of your PD Composition.', 'pressody_conductor' ) . '</p>' .
						'<p>' . sprintf(
						/* translators: %s: https://wordpress.org/themes/ */
							__( 'If you feel adventurous and would like to see more themes to choose from, click on the &#8220;Add New&#8221; button and you will be able to browse or search for additional themes from the <a href="%s">WordPress Theme Directory</a>. Themes in the WordPress Theme Directory are designed and developed by third parties, and are compatible with the license WordPress uses.', 'pressody_conductor' ),
							__( 'https://wordpress.org/themes/', 'pressody_conductor' )
						) . '</p>' .
						'<p>' . __( '<strong>Please note</strong> that we, the crew at Pressody, can\'t take responsibility for the styling and behavior of your site if you use themes outside your PD Composition. We believe we have a diverse and versatile set of styling options to cover most of your needs.' ) . '</p>',
				)
			);
		}

		// Remove the customizer-preview-themes since users will user one of our themes.
		if ( ! ! $screen->get_help_tab( 'customize-preview-themes' ) ) {
			$screen->remove_help_tab( 'customize-preview-themes' );
		}

		// Override the Troubleshooting tab content, if present.
		if ( ! ! $screen->get_help_tab( 'compatibility-problems' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'compatibility-problems',
					'title'   => __( 'Troubleshooting', 'pressody_conductor' ),
					'content' =>
						'<p>' . __( '<strong>Your site\'s PD Composition</strong> is <strong>constantly tested and updated</strong> by us, the crew at Pressody. You should not encounter issues with the various parts, but if you do, <strong>don\'t hesitate to reach us</strong> and we\'ll do our very best to keep your site in tip-top shape.' ) . '</p>' .
						'<p>' . __( '<strong>If you install other WordPress themes</strong> most of the time they will play nicely with the core of WordPress and with other plugins. Sadly, sometimes, a plugin&#8217;s code will get in the way of another plugin, causing compatibility issues. If your site starts doing strange things, this may be the problem. <strong>Try deactivating all the extra plugins</strong> and re-activating them in various combinations until you isolate which one(s) caused the issue.' ) . '</p>' .
						'<p>' . sprintf(
						/* translators: %s: WP_PLUGIN_DIR constant value. */
							__( 'If something goes wrong with a plugin and you can&#8217;t use WordPress, delete or rename that file in the %s directory and it will be automatically deactivated.' ),
							'<code>' . WP_PLUGIN_DIR . '</code>'
						) . '</p>',
				)
			);
		}

		// Override the Auto-updates tab content, if present.
		if ( ! ! $screen->get_help_tab( 'plugins-themes-auto-updates' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'plugins-themes-auto-updates',
					'title'   => __( 'Auto-updates', 'pressody_conductor' ),
					'content' =>
						'<p>' . __( 'Themes delivered and managed by <strong>your site\'s PD Composition</strong> are <strong>updated automatically,</strong> so you don\'t need to worry about them anymore.', 'pressody_conductor' ) . '</p>',
				)
			);
		}
	}

	/**
	 * Alter composition themes data for use in the list table.
	 *
	 * @since 0.11.0
	 *
	 * @param array $prepared_themes Array of theme data.
	 *
	 * @return array
	 */
	public function alter_composition_themes_data_for_list_table( array $prepared_themes ): array {
		$composition_themes       = $this->composition_manager->get_composition_theme();
		$composition_themes_slugs = array_keys( $composition_themes );
		foreach ( $prepared_themes as $theme_slug => $theme_data ) {
			$prepared_themes[ $theme_slug ]['isPDTheme']   = false;

			if ( ! in_array( $theme_slug, $composition_themes_slugs ) ) {
				continue;
			}

			$prepared_themes[ $theme_slug ]['name']   .= '  <em>(PD)</em>';
			$prepared_themes[ $theme_slug ]['isPDTheme']   = true;

			// We handle the updates ourselves
			$prepared_themes[ $theme_slug ]['hasUpdate']   = false;
			$prepared_themes[ $theme_slug ]['update'] = false;
			$prepared_themes[ $theme_slug ]['autoupdate'] = [
				'enabled'   => true,
				'supported' => true,
				'forced'    => true,
			];

			// No deleting the PD composition themes.
			$prepared_themes[ $theme_slug ]['actions']['delete'] = null;
			// Hide certain actions for regular users, not Pressody support.
			if ( ! current_user_has_role( Capabilities::SUPPORT_ROLE ) ) {
				unset( $prepared_themes[ $theme_slug ]['actions']['activate'] );
			}
		}

		return $prepared_themes;
	}

	/**
	 * Filters the JavaScript template used to display the auto-update setting for a theme (in the overlay).
	 *
	 * We need to do this to have a touch of PD in the theme overlay.
	 *
	 * @since 0.11.0
	 *
	 * @param string $template The template for displaying the auto-update setting link.
	 *
	 * @return string
	 */
	public function alter_theme_auto_update_setting_template( string $template ): string {
		$template = '
		<div class="theme-autoupdate">
			<# if ( data.autoupdate.supported ) { #>
				<# if ( data.autoupdate.forced === false ) { #>
					' . __( 'Auto-updates disabled', 'pressody_conductor' ) . '
				<# } else if ( data.autoupdate.forced ) { #>
					<# if ( data.isPDTheme ) { #>
						<strong>' . __( 'Your PD Composition is handling the auto-updates for this theme.<br>One less thing to worry about! 😌', 'pressody_conductor' ) . '</strong>
					<# } else { #>
						' . __( 'Auto-updates enabled', 'pressody_conductor' ) . '
					<# } #>
				<# } else if ( data.autoupdate.enabled ) { #>
					<button type="button" class="toggle-auto-update button-link" data-slug="{{ data.id }}" data-wp-action="disable">
						<span class="dashicons dashicons-update spin hidden" aria-hidden="true"></span><span class="label">' . __( 'Disable auto-updates', 'pressody_conductor' ) . '</span>
					</button>
				<# } else { #>
					<button type="button" class="toggle-auto-update button-link" data-slug="{{ data.id }}" data-wp-action="enable">
						<span class="dashicons dashicons-update spin hidden" aria-hidden="true"></span><span class="label">' . __( 'Enable auto-updates', 'pressody_conductor' ) . '</span>
					</button>
				<# } #>
			<# } #>
			<# if ( data.hasUpdate ) { #>
				<# if ( data.autoupdate.supported && data.autoupdate.enabled ) { #>
					<span class="auto-update-time">
				<# } else { #>
					<span class="auto-update-time hidden">
				<# } #>
				<br />' . wp_get_auto_update_message() . '</span>
			<# } #>
			<div class="notice notice-error notice-alt inline hidden"><p></p></div>
		</div>
	';

		return $template;
	}
}
