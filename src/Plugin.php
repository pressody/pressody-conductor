<?php
/**
 * Main plugin class
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor;

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
		 * Start composing the object graph in Pressody Conductor.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pressody_conductor/compose', $this, $container );

		// Register hook providers.
		$this
			->register_hooks( $container->get( 'hooks.i18n' ) )
			->register_hooks( $container->get( 'hooks.capabilities' ) )
			->register_hooks( $container->get( 'hooks.maintenance' ) )
			->register_hooks( $container->get( 'hooks.rewrite_rules' ) )
			->register_hooks( $container->get( 'hooks.request_handler' ) )
			->register_hooks( $container->get( 'hooks.wpupdates' ) )
			->register_hooks( $container->get( 'composition.manager' ) )
			->register_hooks( $container->get( 'git.manager' ) )
			->register_hooks( $container->get( 'logs.manager' ) );

		if ( is_admin() ) {
			$this
				->register_hooks( $container->get( 'hooks.upgrade' ) )
				->register_hooks( $container->get( 'hooks.admin_assets' ) )
				->register_hooks( $container->get( 'screen.plugins' ) )
				->register_hooks( $container->get( 'screen.themes' ) )
				->register_hooks( $container->get( 'screen.settings' ) )
				->register_hooks( $container->get( 'screen.update-core' ) );
		}

//		if ( \function_exists( 'members_plugin' ) ) {
//			$this->register_hooks( $container->get( 'plugin.members' ) );
//		}

		/**
		 * Finished composing the object graph in Pressody Conductor.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pressody_conductor/composed', $this, $container );
	}

	public function define_constants(): Plugin {
		if ( ! defined( 'Pressody\Conductor\STORAGE_DIR' ) ) {
			define( 'Pressody\Conductor\STORAGE_DIR', \path_join( \PD_ROOT_DIR, 'pd/' ) );
		}

		if ( ! defined( 'Pressody\Conductor\LOG_DIR' ) ) {
			define( 'Pressody\Conductor\LOG_DIR', \path_join( STORAGE_DIR, 'logs/conductor/' ) );
		}

		return $this;
	}

	/**
	 * Register all of our CLI commands.
	 *
	 * @return $this
	 */
	public function register_wp_cli_commands(): Plugin {
		if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
			return $this;
		}

		try {
			\WP_CLI::add_command( 'pd composition', '\Pressody\Conductor\CLI\Composition' );
		} catch ( \Exception $e ) {
			// Nothing right now.
		}

		return $this;
	}
}
