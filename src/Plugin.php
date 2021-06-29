<?php
/**
 * Main plugin class
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor;

use Cedaro\WP\Plugin\Plugin as BasePlugin;
use Psr\Container\ContainerInterface;

/**
 * Main plugin class - composition root.
 *
 * @since 0.1.0
 */
class Plugin extends BasePlugin implements Composable {
	/**
	 * Compose the object graph.
	 *
	 * @since 0.1.0
	 */
	public function compose() {
		$container = $this->get_container();

		/**
		 * Start composing the object graph in PixelgradeLT Conductor.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pixelgradelt_conductor/compose', $this, $container );

		// Register hook providers.
		$this
			->register_hooks( $container->get( 'hooks.i18n' ) )
			->register_hooks( $container->get( 'hooks.capabilities' ) )
			->register_hooks( $container->get( 'hooks.rewrite_rules' ) )
			->register_hooks( $container->get( 'hooks.request_handler' ) )
			->register_hooks( $container->get( 'client.composer.custom_token_auth' ) )

			->register_hooks( $container->get( 'logs.manager' ) );

		if ( is_admin() ) {
			$this
				->register_hooks( $container->get( 'hooks.upgrade' ) )
				->register_hooks( $container->get( 'hooks.admin_assets' ) )
				->register_hooks( $container->get( 'screen.settings' ) );
		}

//		if ( \function_exists( 'members_plugin' ) ) {
//			$this->register_hooks( $container->get( 'plugin.members' ) );
//		}

		/**
		 * Finished composing the object graph in PixelgradeLT Conductor.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pixelgradelt_conductor/composed', $this, $container );
	}

	public function define_constants(): Plugin {
		if ( ! defined( 'PixelgradeLT\Conductor\STORAGE_DIR' ) ) {
			define( 'PixelgradeLT\Conductor\STORAGE_DIR', \path_join( \LT_ROOT_DIR, 'lt/' ) );
		}

		if ( ! defined( 'PixelgradeLT\Conductor\LOG_DIR' ) ) {
			define( 'PixelgradeLT\Conductor\LOG_DIR', \path_join( STORAGE_DIR, 'logs/conductor/' ) );
		}

		if ( ! defined( 'PixelgradeLT\Conductor\COMPOSER_DIR' ) ) {
			define( 'PixelgradeLT\Conductor\COMPOSER_DIR', \path_join( STORAGE_DIR, 'composer/' ) );
		}

		return $this;
	}
}
