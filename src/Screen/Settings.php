<?php
/**
 * Settings screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Conductor\Capabilities;

use function PixelgradeLT\Conductor\get_setting;

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
				esc_html__( 'PixelgradeLT Conductor', 'pixelgradelt_conductor' ),
				esc_html__( 'LT Conductor', 'pixelgradelt_conductor' ),
				Capabilities::MANAGE_OPTIONS,
				'pixelgradelt_conductor',
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
		wp_enqueue_script( 'pixelgradelt_conductor-admin' );
		wp_enqueue_style( 'pixelgradelt_conductor-admin' );
	}

	/**
	 * Register settings.
	 *
	 * @since 0.1.0
	 */
	public function register_settings() {
		register_setting( 'pixelgradelt_conductor', 'pixelgradelt_conductor', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Add settings sections.
	 *
	 * @since 0.1.0
	 */
	public function add_sections() {
		add_settings_section(
				'default',
				esc_html__( 'General', 'pixelgradelt_conductor' ),
				'__return_null',
				'pixelgradelt_conductor'
		);
	}

	/**
	 * Register individual settings.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {
//		add_settings_field(
//				'github-oauth-token',
//				'<label for="pixelgradelt_conductor-github-oauth-token">' . esc_html__( 'Github OAuth Token', 'pixelgradelt_conductor' ) . '</label>',
//				[ $this, 'render_field_github_oauth_token' ],
//				'pixelgradelt_conductor',
//				'default'
//		);
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

//		if ( ! empty( $value['github-oauth-token'] ) ) {
//			$value['github-oauth-token'] = trim( $value['github-oauth-token'] );
//		}

		return (array) apply_filters( 'pixelgradelt_conductor/sanitize_settings', $value );
	}

	/**
	 * Display the screen.
	 *
	 * @since 0.1.0
	 */
	public function render_screen() {

		$tabs = [
				'settings'   => [
						'name'       => esc_html__( 'Settings', 'pixelgradelt_conductor' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
				'system-status'   => [
						'name'       => esc_html__( 'System Status', 'pixelgradelt_conductor' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
		];

		// By default, the Repository tabs is active.
		$active_tab = 'settings';

		include $this->plugin->get_path( 'views/screen-settings.php' );
	}

	/**
	 * Display a field for defining the Github OAuth Token.
	 *
	 * @since 0.1.0
	 */
	public function render_field_github_oauth_token() {
		$value = $this->get_setting( 'github-oauth-token', '' );
		?>
		<p>
			<input type="password" size="80" name="pixelgradelt_conductor[github-oauth-token]"
			       id="pixelgradelt_conductor-github-oauth-token" value="<?php echo esc_attr( $value ); ?>"><br/>
			<span class="description">Github has <strong>a rate limit of 60 requests/hour</strong> on their API for <strong>requests not using an OAuth Token.</strong><br>
				Since most packages on Packagist.org have their source on Github, and you may be using actual Github repos as sources, <strong>you should definitely generate a token and save it here.</strong><br>
				Learn more about <strong>the steps to take <a
							href="https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth">here</a>.</strong> <strong>Be careful about the permissions you grant on the generated token!</strong></span>
		</p>
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
