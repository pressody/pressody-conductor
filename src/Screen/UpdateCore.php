<?php
/**
 * Update Core screen provider.
 *
 * @since   0.12.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Conductor\Capabilities;
use PixelgradeLT\Conductor\Composition\CompositionManager;
use function PixelgradeLT\Conductor\current_user_has_role;

/**
 * Update Core screen provider class.
 *
 * @since 0.12.0
 */
class UpdateCore extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 0.12.0
	 */
	public function register_hooks() {
		add_action( 'admin_head-update-core.php', [ $this, 'change_screen_meta' ] );

		add_action( 'all_admin_notices', [ $this, 'maybe_start_output_buffering' ], 9999 );
		add_action( 'core_upgrade_preamble', [ $this, 'maybe_end_output_buffering' ], 9999 );
	}

	/**
	 * Change the screen's meta (the Help section) to better reflect the LT reality.
	 *
	 * @since 0.12.0
	 */
	public function change_screen_meta() {
		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return;
		}

		// Override the `Overview` tab content, if present.
		if ( ! ! $screen->get_help_tab( 'overview' ) ) {
			$updates_overview = '<p>' . __( 'On this screen, you can update your non-LT themes, plugins, and translations from the WordPress.org repositories.', 'pixelgradelt_conductor' ) . '</p>';
			$updates_overview .= '<p>' . __( '<strong>The WordPress core files, plugins and themes</strong> delivered and managed by <strong>your site\'s LT Composition</strong> are <strong>updated automatically,</strong> so you don\'t need to worry about them anymore.', 'pixelgradelt_conductor' ) . '</p>';
			$updates_overview .= '<p>' . __( 'If an update is available, you&#8127;ll see a notification appear in the Toolbar and navigation menu.', 'pixelgradelt_conductor' ) . ' ' . __( 'Keeping your site updated is <strong>important for security.</strong> It also <strong>makes the internet a safer place</strong> for you and your readers.', 'pixelgradelt_conductor' ) . '</p>';

			$screen->add_help_tab(
				array(
					'id'      => 'overview',
					'title'   => __( 'Overview', 'pixelgradelt_conductor' ),
					'content' => $updates_overview,
				)
			);
		}

		// Override the `How to Update` tab content, if present.
		if ( ! ! $screen->get_help_tab( 'how-to-update' ) ) {
			$updates_howto = '<p>' . __( '<strong>WordPress</strong> &mdash; Your site\'s WordPress files are <strong>kept up-to-date by your LT Composition,</strong> as promised. One less thing to worry about!', 'pixelgradelt_conductor' ) . '</p>';
			$updates_howto .= '<p>' . __( '<strong>Themes and Plugins</strong> &mdash; To update individual themes or plugins from this screen, use the checkboxes to make your selection, then <strong>click on the appropriate &#8220;Update&#8221; button</strong>. To update all of your themes or plugins at once, you can check the box at the top of the section to select all before clicking the update button.', 'pixelgradelt_conductor' ) . '</p>';

			if ( 'en_US' !== get_locale() ) {
				$updates_howto .= '<p>' . __( '<strong>Translations</strong> &mdash; The files translating WordPress into your language are updated for you whenever any other updates occur. But if these files are out of date, you can <strong>click the &#8220;Update Translations&#8221;</strong> button.' ) . '</p>';
			}

			$screen->add_help_tab(
				array(
					'id'      => 'how-to-update',
					'title'   => __( 'How to Update', 'pixelgradelt_conductor' ),
					'content' => $updates_howto,
				)
			);
		}

		// Override the `Auto-updates` tab content, if present.
		if ( ! ! $screen->get_help_tab( 'plugins-themes-auto-updates' ) ) {
			$screen->add_help_tab(
				array(
					'id'      => 'plugins-themes-auto-updates',
					'title'   => __( 'Auto-updates', 'pixelgradelt_conductor' ),
					'content' =>
						'<p>' . __( 'The WordPress core files, plugins and themes delivered and managed by <strong>your site\'s LT Composition</strong> are <strong>updated automatically,</strong> so you don\'t need to worry about them anymore.', 'pixelgradelt_conductor' ) . '</p>' .
						'<p>' . __( 'Plugins and themes that you\'ve installed besides the LT Composition, with auto-updates enabled, will display the estimated date of the next auto-update.', 'pixelgradelt_conductor' ) . '</p>' .
						'<p>' . __( 'Auto-updates are only available for plugins recognized by WordPress.org, or that include a compatible update system.', 'pixelgradelt_conductor' ) . '</p>',
				)
			);
		}
	}

	/**
	 * Maybe start output buffering so we can change some things not possible through hooks.
	 *
	 * @since 0.12.0
	 */
	public function maybe_start_output_buffering() {
		// No changes for support people.
		if ( current_user_has_role( Capabilities::SUPPORT_ROLE ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || 'update-core' !== $screen->id ) {
			return;
		}

		ob_start();
	}

	/**
	 * Maybe end output buffering and change some things not possible through hooks.
	 *
	 * @since 0.12.0
	 */
	public function maybe_end_output_buffering() {
		// No changes for support people.
		if ( current_user_has_role( Capabilities::SUPPORT_ROLE ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || 'update-core' !== $screen->id ) {
			return;
		}

		// We don't want to enforce the presence of ext-libdom and ext-libxml PHP extensions,
		// although on most systems they should be available.
		// Do nothing if the extensions are not present.
		if ( ! class_exists( '\DOMDocument' )
		     || ! class_exists( '\DOMXPath' )
		     || ! function_exists( 'libxml_use_internal_errors' )
		     || ! function_exists( 'libxml_clear_errors' )
		     || ! defined( 'LIBXML_HTML_NOIMPLIED' )
		     || ! defined( 'LIBXML_HTML_NODEFDTD' ) ) {

			ob_end_flush();

			return;
		}

		$output = ob_get_clean();
		if ( false === $output ) {
			return;
		}

		$dom                  = new \DOMDocument();
		$dom->validateOnParse = false;
		\libxml_use_internal_errors( true );
		// Use LIBXML for preventing output of doctype, <html>, and <body> tags
		$dom->loadHTML( $output, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		// We are not interested in parsing errors right now (like duplicate elements warnings, etc).
		\libxml_clear_errors();

		$xpath    = new \DOMXPath( $dom );
		$elements = $xpath->query( '//p[@class="update-last-checked"]' );
		// Avoid outputting the parsed HTML if we don't find the nodes we are after.
		if ( empty( $elements ) ) {
			echo $output;

			return;
		}
		/** @var \DOMNode $el */
		foreach ( $elements as $el ) {
			// Remove the child nodes.
			while ( $el->hasChildNodes() ) {
				$el->removeChild( $el->firstChild );
			}

			// Now add our replacement inner HTML of the node.
			$frag = $dom->createDocumentFragment();
			$frag->appendXML( '<p class="update-last-checked">' . __( 'WordPress is <strong>automatically kept up-to-date by your Pixelgrade LT Composition,</strong> as promised.', 'pixelgradelt_conductor') . '</p>' );

			$el->parentNode->replaceChild( $frag, $el );
		}

		// No need for the update status.
		$elements = $xpath->query( 'p[@class="auto-update-status"]' );
		if ( ! empty( $elements ) ) {
			/** @var \DOMNode $el */
			foreach ( $elements as $el ) {
				$el->parentNode->removeChild( $el );
			}
		}

		echo $dom->saveHTML();
	}
}
