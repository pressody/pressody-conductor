<?php
/**
 * Git management and integration routines for the entire LT site files.
 *
 * Packages (plugins, themes) delivered and updated by the composition via Composer should not be pushed to Git.
 *
 * Heavily inspired by and borrowed from Gitium by Presslabs: https://github.com/presslabs/gitium
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Git;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Conductor\Composition\CompositionManager;
use PixelgradeLT\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use function PixelgradeLT\Conductor\is_plugin_file;
use function PixelgradeLT\Conductor\plugin;

/**
 * Class to manage the Git integration of the site.
 *
 * @since 0.10.0
 */
class GitManager extends AbstractHookProvider {

	const VERSIONS_TRANSIENT = 'pixelgradelt_conductor_git_versions';

	/**
	 * The Git client.
	 *
	 * @since 0.10.0
	 *
	 * @var GitClientInterface
	 */
	protected GitClientInterface $git_client;

	/**
	 * The composition manager.
	 *
	 * @since 0.10.0
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Queue.
	 *
	 * @since 0.10.0
	 *
	 * @var QueueInterface
	 */
	protected QueueInterface $queue;

	/**
	 * Logger.
	 *
	 * @since 0.10.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param GitClientInterface $git_client          The Git client.
	 * @param CompositionManager $composition_manager The composition manager.
	 * @param QueueInterface     $queue               Queue.
	 * @param LoggerInterface    $logger              Logger.
	 */
	public function __construct(
		GitClientInterface $git_client,
		CompositionManager $composition_manager,
		QueueInterface $queue,
		LoggerInterface $logger
	) {
		$this->git_client          = $git_client;
		$this->composition_manager = $composition_manager;
		$this->queue               = $queue;
		$this->logger              = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.10.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_recurring_events' );

		// Each time the composition DB cache gets updated, update the .gitignore file with the composition plugins and themes.
		add_action( 'update_option_' . $this->composition_manager::COMPOSITION_PLUGINS_OPTION_NAME, [
			$this,
			'maybe_updated_gitignore_on_update_composer_plugins',
		] );
		add_action( 'update_option_' . $this->composition_manager::COMPOSITION_THEMES_OPTION_NAME, [
			$this,
			'maybe_update_gitignore_on_update_composer_themes',
		] );

		// Hook in all the places that might generate site file changes.
		add_filter( 'upgrader_post_install', [ $this, 'on_upgrader_post_install' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'git_auto_push' ], 11, 0 );
		add_action( 'activated_plugin', [ $this, 'check_after_plugin_activate' ], 999, 1 );
		add_action( 'deactivated_plugin', [ $this, 'check_after_plugin_deactivate' ], 999, 1 );
		add_action( 'deleted_plugin', [ $this, 'check_after_plugin_deleted' ], 999, 2 );
		add_action( 'deleted_theme', [ $this, 'check_after_theme_deleted' ], 999, 2 );
	}

	/**
	 * Maybe schedule the recurring actions/events, if it is not already scheduled.
	 *
	 * @since 0.10.0
	 */
	protected function schedule_recurring_events() {
		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pixelgradelt_conductor/midnight', [], 'plt_con' );
		}

		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pixelgradelt_conductor/hourly', [], 'plt_con' );
		}
	}

	/**
	 * Update the site's .gitignore file with the plugins installed by Composer.
	 *
	 * This way we only ignore what we install, leaving the user to manually install things.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function maybe_updated_gitignore_on_update_composer_plugins(): bool {
		$contents = $this->git_client->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_plugins_dir_path = ltrim( str_replace( LT_ROOT_DIR, '', WP_PLUGIN_DIR ), '/' );

		$plugins     = $this->composition_manager->get_composition_plugin();
		$ignore_list = array_keys( $plugins );
		$ignore_list = array_map( function ( $ignore ) use ( $relative_plugins_dir_path ) {
			return dirname( \path_join( $relative_plugins_dir_path, $ignore ) );
		}, $ignore_list );

		$contents = preg_replace(
			'/(#\s*composition_wp_plugins_start\s*#)(\r\n|\r|\n).*(#\s*composition_wp_plugins_end\s*#)/is',
			'$1$2' . implode( PHP_EOL, $ignore_list ) . ( ! empty( $ignore_list ) ? PHP_EOL : '' ) . '$3',
			$contents
		);

		return $this->git_client->write_gitignore( $contents );
	}

	/**
	 * Update the site's .gitignore file with the themes installed by Composer.
	 *
	 * This way we only ignore what we install, leaving the user to manually install things.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function maybe_update_gitignore_on_update_composer_themes(): bool {
		$contents = $this->git_client->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_themes_dir_path = ltrim( str_replace( LT_ROOT_DIR, '', WP_CONTENT_DIR . '/themes' ), '/' );

		$themes      = $this->composition_manager->get_composition_theme();
		$ignore_list = array_keys( $themes );
		$ignore_list = array_map( function ( $ignore ) use ( $relative_themes_dir_path ) {
			return \path_join( $relative_themes_dir_path, $ignore );
		}, $ignore_list );

		$contents = preg_replace(
			'/(#\s*composition_wp_themes_start\s*#)(\r\n|\r|\n).*(#\s*composition_wp_themes_end\s*#)/is',
			'$1$2' . implode( PHP_EOL, $ignore_list ) . ( ! empty( $ignore_list ) ? PHP_EOL : '' ) . '$3',
			$contents
		);

		return $this->git_client->write_gitignore( $contents );
	}

	/**
	 * Filters the installation response after the installation has finished.
	 *
	 * @since 0.10.0
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 */
	public function on_upgrader_post_install( bool $response, array $hook_extra, array $result ): bool {
		_gitium_make_ssh_git_file_exe();

		$action = null;
		$type   = null;

		// install logic
		if ( isset( $hook_extra['type'] ) && ( 'plugin' === $hook_extra['type'] ) ) {
			$action = 'installed';
			$type   = 'plugin';
		} else if ( isset( $hook_extra['type'] ) && ( 'theme' === $hook_extra['type'] ) ) {
			$action = 'installed';
			$type   = 'theme';
		}

		// update/upgrade logic
		if ( isset( $hook_extra['plugin'] ) ) {
			$action = 'updated';
			$type   = 'plugin';
		} else if ( isset( $hook_extra['theme'] ) ) {
			$action = 'updated';
			$type   = 'theme';
		}

		// get action if missed above
		if ( isset( $hook_extra['action'] ) ) {
			$action = $hook_extra['action'];
			if ( 'install' === $action ) {
				$action = 'installed';
			}
			if ( 'update' === $action ) {
				$action = 'updated';
			}
		}

		$name    = $result['destination_name'];
		$version = '';

		$git_dir = $result['destination'];
		if ( ABSPATH == substr( $git_dir, 0, strlen( ABSPATH ) ) ) {
			$git_dir = substr( $git_dir, strlen( ABSPATH ) );
		}
		switch ( $type ) {
			case 'theme':
				wp_clean_themes_cache();
				$theme_data = wp_get_theme( $result['destination_name'] );
				$name       = $theme_data->get( 'Name' );
				$version    = $theme_data->get( 'Version' );
				break;
			case 'plugin':
				foreach ( $result['source_files'] as $file ) :
					if ( '.php' != substr( $file, - 4 ) ) {
						continue;
					}
					// every .php file is a possible plugin so we check if it's a plugin
					$filepath    = trailingslashit( $result['destination'] ) . $file;
					$plugin_data = get_plugin_data( $filepath );
					if ( $plugin_data['Name'] ) :
						$name    = $plugin_data['Name'];
						$version = $plugin_data['Version'];
						// We get info from the first plugin in the package
						break;
					endif;
				endforeach;
				break;
		}

		$message = '{change_action} {change_type} {name}';
		$context = [
			'change_action' => $action,
			'change_type'   => $type,
			'name'          => $name,
		];
		if ( ! empty( $version ) ) {
			$message            .= ' version {version}';
			$context['version'] = $version;
		}
		$message = $this->git_client->format_message( $message, $context );

		$commit = $this->git_client->commit_changes( $message, $git_dir );
		$this->git_client->merge_and_push( $commit );

		$this->refresh_plugin_and_theme_versions_cache();

		// We just let the filtered response pass through.
		return $response;
	}

	/**
	 * Commit possible changes after plugin activation.
	 *
	 * @since 0.10.0
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 */
	public function check_after_plugin_activate( string $plugin_file ) {
		$this->check_after_event( $plugin_file );
	}

	/**
	 * Commit possible changes after plugin deactivation.
	 *
	 * @since 0.10.0
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 */
	public function check_after_plugin_deactivate( string $plugin_file ) {
		$this->check_after_event( $plugin_file, 'deactivation' );
	}

	/**
	 * Commit changes after plugin deletion.
	 *
	 * @since 0.10.0
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param bool   $deleted     Whether the plugin deletion was successful.
	 */
	public function check_after_plugin_deleted( string $plugin_file, bool $deleted ) {
		if ( ! $deleted ) {
			return;
		}

		$this->check_after_event( $plugin_file, 'deletion' );
	}

	/**
	 * Commit changes after theme deletion.
	 *
	 * @since 0.10.0
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @param bool   $deleted    Whether the theme deletion was successful.
	 */
	public function check_after_theme_deleted( string $stylesheet, bool $deleted ) {
		if ( ! $deleted ) {
			return;
		}

		$this->check_after_event( $stylesheet, 'deletion' );
	}

	protected function check_after_event( string $path, $event = 'activation' ) {
		// Do not hook on activation of this plugin
		if ( plugin()->get_basename() == $path ) {
			return;
		}

		if ( $this->git_client->is_dirty() ) {
			$name    = $path;
			$version = '';
			if ( is_plugin_file( $path ) && $plugin_data = get_plugins( $path ) ) {
				$name    = $plugin_data['Name'];
				$version = $plugin_data['Version'];
			} else {
				$theme = wp_get_theme( $path );
				if ( $theme->exists() ) {
					$name    = $theme->get( 'Name' );
					$version = $theme->get( 'Version' );
				}
			}

			$message = 'after {event} of {name}';
			$context = [
				'event' => $event,
				'name'  => $name,
			];
			if ( ! empty( $version ) ) {
				$message            .= ' version {version}';
				$context['version'] = $version;
			}
			$this->git_auto_push( $this->git_client->format_message( $message, $context ) );
		}
	}

	/**
	 * Checks for local changes, tries to group them by plugin/theme and pushes the changes.
	 *
	 * @since 0.10.0
	 *
	 * @param string $msg_prepend Optional. Message part to prepend to commit message.
	 */
	public function git_auto_push( string $msg_prepend = '' ) {
		$commits = $this->group_commit_modified_plugins_and_themes( $msg_prepend );
		$this->git_client->merge_and_push( $commits );
		$this->refresh_plugin_and_theme_versions_cache();
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $msg_append
	 *
	 * @return array
	 */
	public function group_commit_modified_plugins_and_themes( string $msg_append = '' ): array {
		$not_committed_changes = $this->git_client->get_local_changes();
		$commit_groups         = [];
		$commits               = [];

		if ( ! empty( $msg_append ) ) {
			$msg_append = "($msg_append)";
		}
		foreach ( $not_committed_changes as $path => $action ) {
			$change                                = $this->module_by_path( $path );
			$change['action']                      = $action;
			$commit_groups[ $change['base_path'] ] = $change;
		}

		foreach ( $commit_groups as $base_path => $change ) {
			$message = '{change_action} {change_type} {name}';
			$context = [
				'change_action' => $change['action'],
				'change_type'   => $change['type'],
				'name'          => $change['name'],
			];
			if ( ! empty( $change['version'] ) ) {
				$message            .= ' version {version}';
				$context['version'] = $change['version'];
			}
			$message = $this->git_client->format_message( $message . $msg_append, $context );
			$commit  = $this->git_client->commit_changes( $message, $base_path );
			if ( $commit ) {
				$commits[] = $commit;
			}
		}

		return $commits;
	}

	/**
	 * This function return the basic info about a path.
	 *
	 * base_path - means the path after wp-content dir (themes/plugins)
	 * type      - can be file/theme/plugin
	 * name      - the file name of the path, if it is a file, or the theme/plugin name
	 * version   - the theme/plugin version, otherwise null
	 *
	 * Some examples:
	 * with 'wp-content/themes/twentyten/style.css' will return:
	 * array(
	 *  'base_path' => 'wp-content/themes/twentyten'
	 *  'type'      => 'theme'
	 *  'name'      => 'TwentyTen'
	 *  'version'   => '1.12'
	 * )
	 *
	 * with 'wp-content/themes/twentyten/img/foo.png' will return:
	 * array(
	 *  'base_path' => 'wp-content/themes/twentyten'
	 *  'type'      => 'theme'
	 *  'name'      => 'TwentyTen'
	 *  'version'   => '1.12'
	 * )
	 *
	 * with 'wp-content/plugins/foo.php' will return:
	 * array(
	 *  'base_path' => 'wp-content/plugins/foo.php'
	 *  'type'      => 'plugin'
	 *  'name'      => 'Foo'
	 *  'version'   => '2.0'
	 * )
	 *
	 * with 'wp-content/plugins/autover/autover.php' will return:
	 * array(
	 *  'base_path' => 'wp-content/plugins/autover'
	 *  'type'      => 'plugin'
	 *  'name'      => 'autover'
	 *  'version'   => '3.12'
	 * )
	 *
	 * with 'wp-content/plugins/autover/' will return:
	 * array(
	 *  'base_path' => 'wp-content/plugins/autover'
	 *  'type'      => 'plugin'
	 *  'name'      => 'autover'
	 *  'version'   => '3.12'
	 * )
	 *
	 * @since 0.10.0
	 *
	 * @param $path
	 *
	 * @return array
	 */
	protected function module_by_path( $path ): array {
		$versions = $this->get_plugin_and_theme_versions();

		// default values
		$module = array(
			'base_path' => $path,
			'type'      => 'file',
			'name'      => basename( $path ),
			'version'   => null,
		);

		// find the base_path
		$split_path = explode( '/', $path );
		if ( 2 < count( $split_path ) ) {
			$module['base_path'] = "{$split_path[0]}/{$split_path[1]}/{$split_path[2]}";
		}

		// find other data for theme
		if ( array_key_exists( 'themes', $versions ) && 0 === strpos( $path, 'wp-content/themes/' ) ) {
			$module['type'] = 'theme';
			foreach ( $versions['themes'] as $theme => $data ) {
				if ( 0 === strpos( $path, "wp-content/themes/$theme" ) ) {
					$module['name']    = $data['name'];
					$module['version'] = $data['version'];
					break;
				}
			}
		}

		// find other data for plugin
		if ( array_key_exists( 'plugins', $versions ) && 0 === strpos( $path, 'wp-content/plugins/' ) ) {
			$module['type'] = 'plugin';
			foreach ( $versions['plugins'] as $plugin => $data ) {
				if ( '.' === dirname( $plugin ) ) { // single file plugin
					if ( "wp-content/plugins/$plugin" === $path ) {
						$module['base_path'] = $path;
						$module['name']      = $data['name'];
						$module['version']   = $data['version'];
						break;
					}
				} else if ( 'wp-content/plugins/' . dirname( $plugin ) === $module['base_path'] ) {
					$module['name']    = $data['name'];
					$module['version'] = $data['version'];
					break;
				}
			}
		}

		return $module;
	}

	/**
	 * Get the cached plugins and themes versions list.
	 *
	 * @since 0.10.0
	 *
	 * @return array|mixed
	 */
	protected function get_plugin_and_theme_versions() {
		$versions = get_transient( self::VERSIONS_TRANSIENT );
		if ( empty( $versions ) ) {
			$versions = $this->refresh_plugin_and_theme_versions_cache();
		}

		return $versions;
	}

	/**
	 * Refresh the cached plugins and themes versions list.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function refresh_plugin_and_theme_versions_cache(): array {
		$new_versions = [];

		// get all themes from WP
		$all_themes = wp_get_themes( array( 'allowed' => true ) );
		foreach ( $all_themes as $theme_name => $theme ) {
			$theme_versions[ $theme_name ]        = array(
				'name'    => $theme->get( 'Name' ),
				'version' => null,
				'msg'     => '',
			);
			$theme_versions[ $theme_name ]['msg'] = '`' . $theme->get( 'Name' ) . '`';
			$version                              = $theme->get( 'Version' );
			if ( ! empty( $version ) ) {
				$theme_versions[ $theme_name ]['msg']     .= " version $version";
				$theme_versions[ $theme_name ]['version'] .= $version;
			}
		}

		if ( ! empty( $theme_versions ) ) {
			$new_versions['themes'] = $theme_versions;
		}
		// get all plugins from WP
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $name => $data ) {
			$plugin_versions[ $name ]        = array(
				'name'    => $data['Name'],
				'version' => null,
				'msg'     => '',
			);
			$plugin_versions[ $name ]['msg'] = "`{$data['Name']}`";
			if ( ! empty( $data['Version'] ) ) {
				$plugin_versions[ $name ]['msg']     .= ' version ' . $data['Version'];
				$plugin_versions[ $name ]['version'] .= $data['Version'];
			}
		}

		if ( ! empty( $plugin_versions ) ) {
			$new_versions['plugins'] = $plugin_versions;
		}

		set_transient( self::VERSIONS_TRANSIENT, $new_versions, 3 * DAY_IN_SECONDS );

		return $new_versions;
	}

	/**
	 * Commit the .gitignore file and push.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function commit_and_push_gitignore_file(): bool {
		$commit = $this->git_client->commit_changes( 'Update the `.gitignore` file', '.gitignore' );

		return $this->git_client->merge_and_push( $commit );
	}
}
