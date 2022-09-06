<?php
/**
 * Git management and integration routines for the entire PD site files.
 *
 * Packages (plugins, themes) delivered and updated by the composition via Composer should not be pushed to Git.
 *
 * Heavily inspired by and borrowed from Gitium by Presslabs: https://github.com/presslabs/gitium
 *
 * @since   0.10.0
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

namespace Pressody\Conductor\Git;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Composition\CompositionManager;
use Pressody\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use function Pressody\Conductor\is_plugin_file;
use function Pressody\Conductor\plugin;

/**
 * Class to manage the Git integration of the site.
 *
 * @since 0.10.0
 */
class GitManager extends AbstractHookProvider {

	const DETAILS_TRANSIENT = 'pressody_conductor_git_details';

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
			'maybe_update_gitignore_on_update_composer_plugins',
		] );
		add_action( 'update_option_' . $this->composition_manager::COMPOSITION_THEMES_OPTION_NAME, [
			$this,
			'maybe_update_gitignore_on_update_composer_themes',
		] );

		// Hook the git logic in all the places that might generate site file changes.
		// But only if we actually have a git repo and that we can interact with it (like running git commands).
		// If the WordPress environment is in `development` mode we will not do anything to avoid getting in the way.
		if ( ( ! defined( 'WP_ENV' ) || \WP_ENV !== 'development' ) && $this->git_client->can_interact() ) {
			add_filter( 'upgrader_post_install', [ $this, 'on_upgrader_post_install' ], 10, 3 );
			add_action( 'upgrader_process_complete', [ $this, 'schedule_git_auto_push' ], 11, 0 );
			add_action( 'activated_plugin', [ $this, 'check_after_plugin_activate' ], 999, 1 );
			add_action( 'deactivated_plugin', [ $this, 'check_after_plugin_deactivate' ], 999, 1 );
			add_action( 'deleted_plugin', [ $this, 'check_after_plugin_deleted' ], 999, 2 );
			add_action( 'deleted_theme', [ $this, 'check_after_theme_deleted' ], 999, 2 );

			// Scheduled actions.
			add_action( 'pressody_conductor/git/auto_push', [ $this, 'git_auto_push' ], 10, 1 );
			add_action( 'pressody_conductor/git/merge_and_push', [ $this, 'git_merge_and_push' ], 10, 1 );
			add_action( 'pressody_conductor/hourly', [ $this, 'git_auto_push' ], 99, 0 );
		}
	}

	/**
	 * Maybe schedule the recurring actions/events, if they are not already scheduled.
	 *
	 * @since 0.10.0
	 */
	protected function schedule_recurring_events() {
		if ( ! $this->queue->get_next( 'pressody_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pressody_conductor/midnight', [], 'plt_con' );
		}

		if ( ! $this->queue->get_next( 'pressody_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pressody_conductor/hourly', [], 'plt_con' );
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
	public function maybe_update_gitignore_on_update_composer_plugins(): bool {
		$contents = $original_contents = $this->git_client->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_plugins_dir_path = ltrim( str_replace( PD_ROOT_DIR, '', WP_PLUGIN_DIR ), '/' );

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

		if ( $contents === $original_contents ) {
			return false;
		}

		if ( $result = $this->git_client->write_gitignore( $contents ) ) {
			$this->commit_and_push_gitignore_file( 'after composition plugins changes' );
		}

		return $result;
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
		$contents = $original_contents = $this->git_client->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_themes_dir_path = ltrim( str_replace( PD_ROOT_DIR, '', WP_CONTENT_DIR . '/themes' ), '/' );

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

		if ( $contents === $original_contents ) {
			return false;
		}

		if ( $result = $this->git_client->write_gitignore( $contents ) ) {
			$this->commit_and_push_gitignore_file( 'after composition themes changes' );
		}

		return $result;
	}

	/**
	 * Filters the installation response after an installation has finished.
	 *
	 * @since 0.10.0
	 *
	 * @param bool  $response   Installation response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 */
	public function on_upgrader_post_install( bool $response, array $hook_extra, array $result ): bool {
		$action = null;
		$type   = null;

		// Install logic.
		if ( isset( $hook_extra['type'] ) && ( 'plugin' === $hook_extra['type'] ) ) {
			$action = 'install';
			$type   = 'plugin';
		} else if ( isset( $hook_extra['type'] ) && ( 'theme' === $hook_extra['type'] ) ) {
			$action = 'install';
			$type   = 'theme';
		}

		// Update/upgrade logic.
		if ( isset( $hook_extra['plugin'] ) ) {
			$action = 'update';
			$type   = 'plugin';
		} else if ( isset( $hook_extra['theme'] ) ) {
			$action = 'update';
			$type   = 'theme';
		}

		// Get action if missed above.
		if ( isset( $hook_extra['action'] ) ) {
			$action = $hook_extra['action'];
			if ( 'install' === $action ) {
				$action = 'install';
			}
			if ( 'update' === $action ) {
				$action = 'update';
			}
		}

		$name    = $result['destination_name'];
		$version = '';

		$path = $this->make_path_relative_to_git_root( $result['destination'] );

		switch ( $type ) {
			case 'theme':
				\wp_clean_themes_cache();
				$theme_data = \wp_get_theme( $result['destination_name'] );
				if ( $theme_data->exists() ) {
					$name    = $theme_data->get( 'Name' );
					$version = $theme_data->get( 'Version' );
				}
				break;
			case 'plugin':
				foreach ( $result['source_files'] as $file ) {
					if ( ! is_plugin_file( $file ) ) {
						continue;
					}
					// Every .php file is a possible plugin, so we check if it's a plugin.
					$filepath    = \trailingslashit( $result['destination'] ) . $file;
					$plugin_data = \get_plugin_data( $filepath );

					// We get info from the first plugin in the package.
					if ( ! empty( $plugin_data['Name'] ) ) {
						$name    = $plugin_data['Name'];
						$version = $plugin_data['Version'];
						break;
					}
				}
				break;
			default:
				break;
		}

		$message = '{change_action} {change_type} `{name}`';
		$context = [
			'change_action' => ucfirst( $action ),
			'change_type'   => $type,
			'name'          => $name,
		];
		if ( ! empty( $version ) ) {
			$message            .= ' (version {version})';
			$context['version'] = $version;
		}

		// We will commit now and schedule the expensive merge and push.
		$message = $this->git_client->format_message( $message, $context );
		$commit  = $this->git_client->commit_changes( $message, $path );

		$this->schedule_git_merge_and_push( $commit );

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
		$this->check_after_event( $plugin_file, 'activation' );
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
		if ( plugin()->get_basename() === $path ) {
			return;
		}

		if ( ! $this->git_client->is_repo_dirty() ) {
			return;
		}

		$name    = $path;
		$version = '';
		if ( is_plugin_file( $path ) ) {
			$plugin_data = \get_plugins( '/' . dirname( $path ) );
			$plugin_data = reset( $plugin_data );
			if ( $plugin_data ) {
				$name    = $plugin_data['Name'];
				$version = $plugin_data['Version'];
			}
		} else {
			$theme = \wp_get_theme( $path );
			if ( $theme->exists() ) {
				$name    = $theme->get( 'Name' );
				$version = $theme->get( 'Version' );
			}
		}

		$message_append = 'after {event} of `{name}`';
		$context        = [
			'event' => $event,
			'name'  => $name,
		];
		if ( ! empty( $version ) ) {
			$message_append     .= ' (version {version})';
			$context['version'] = $version;
		}

		$this->schedule_git_auto_push( $this->git_client->format_message( $message_append, $context ) );
	}

	/**
	 * Schedule the async event to git auto-push any changes in the repo.
	 *
	 * @since 0.10.0
	 */
	protected function schedule_git_auto_push( string $msg_append = '' ) {
		if ( ! $this->queue->get_next( 'pressody_conductor/git/auto_push' ) ) {
			$this->queue->add( 'pressody_conductor/git/auto_push', [ $msg_append ], 'plt_con' );
		}
	}

	/**
	 * Checks for local changes, tries to group them by plugin/theme and pushes the changes.
	 *
	 * @since 0.10.0
	 *
	 * @param string $msg_append Optional. Message part to append to commit message.
	 */
	public function git_auto_push( string $msg_append = '' ) {
		$commits = $this->group_commit_modified_plugins_and_themes( $msg_append );
		if ( ! empty( $commits ) ) {
			$this->git_merge_and_push( $commits );
		}
	}

	/**
	 * Schedule the async event to git merge and push any local commits.
	 *
	 * @since 0.10.0
	 *
	 * @param array|string|false $commits A commit hash, a list of commit hashes or false for no commits.
	 */
	protected function schedule_git_merge_and_push( $commits = false ) {
		if ( ! $this->queue->get_next( 'pressody_conductor/git/merge_and_push' ) ) {
			$this->queue->add( 'pressody_conductor/git/merge_and_push', [ $commits ], 'plt_con' );
		}
	}

	/**
	 * Merges any remote changes and pushes local commits.
	 *
	 * Does not commit unstaged changes. Use self::git_auto_push() for that.
	 *
	 * @since 0.10.0
	 *
	 * @param array|string|false $commits A commit hash, a list of commit hashes or false for no specific commits to cherry-pick.
	 *
	 * @return bool
	 */
	public function git_merge_and_push( $commits = false ): bool {
		if ( $result = $this->git_client->merge_and_push( $commits ) ) {
			$this->refresh_plugin_and_theme_details_cache();
		}

		return $result;
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
			$msg_append = " - $msg_append";
		}
		foreach ( $not_committed_changes as $path => $action ) {
			$change                                = $this->module_by_path( $path );
			$change['action']                      = $action;
			$commit_groups[ $change['base_path'] ] = $change;
		}

		foreach ( $commit_groups as $base_path => $change ) {
			$message = '{change_action} {change_type} `{name}`';
			$context = [
				'change_action' => ucfirst( $change['action'] ),
				'change_type'   => $change['type'],
				'name'          => $change['name'],
			];
			if ( ! empty( $change['version'] ) ) {
				$message            .= ' (version {version})';
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
	 * base_path - means the relative path to the module root
	 * type      - can be `file`, `theme`, `plugin`, or `mu-plugin`
	 * name      - the file name of the path, if it is a file, or the theme/plugin name
	 * version   - the theme/plugin version, otherwise null
	 *
	 * Some examples:
	 * with '.gitignore' will return:
	 * array(
	 *  'base_path' => '.gitignore'
	 *  'type'      => 'file'
	 *  'name'      => '.gitignore'
	 *  'version'   => null
	 * )
	 *
	 * with 'web/app/themes/twentyten/style.css' will return:
	 * array(
	 *  'base_path' => 'web/app/themes/twentyten'
	 *  'type'      => 'theme'
	 *  'name'      => 'TwentyTen'
	 *  'version'   => '1.12'
	 * )
	 *
	 * with 'web/app/themes/twentyten/img/foo.png' will return:
	 * array(
	 *  'base_path' => 'web/app/themes/twentyten'
	 *  'type'      => 'theme'
	 *  'name'      => 'TwentyTen'
	 *  'version'   => '1.12'
	 * )
	 *
	 * with 'web/app/plugins/foo.php' will return:
	 * array(
	 *  'base_path' => 'web/app/plugins/foo.php'
	 *  'type'      => 'plugin'
	 *  'name'      => 'Foo'
	 *  'version'   => '2.0'
	 * )
	 *
	 * with 'web/app/plugins/autover/autover.php' will return:
	 * array(
	 *  'base_path' => 'web/app/plugins/autover'
	 *  'type'      => 'plugin'
	 *  'name'      => 'autover'
	 *  'version'   => '3.12'
	 * )
	 *
	 * with 'web/app/plugins/autover/' will return:
	 * array(
	 *  'base_path' => 'web/app/plugins/autover'
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

		/* =====================
		 * We want to handle all changes in a module (plugin or theme) together.
		 * So we want to use the base path of that module, if the path given is somewhere within it.
		 */

		/*
		 * First, make sure that we are dealing with a path relative to the root of the site (the place that is the root of the git repo).
		 */
		$path = $this->make_path_relative_to_git_root( $path );

		// Default module details.
		$module = array(
			'base_path' => $path,
			'type'      => 'file',
			'name'      => basename( $path ),
			'version'   => null,
		);

		/*
		 * Second, if we have a path to a plugin or a theme, limit it to it's root.
		 */
		$app_dir = $this->get_relative_app_dir_path();

		// Get the cached plugins and themes details.
		$details = $this->get_plugin_and_theme_details();

		$themes_dir_path    = \path_join( $app_dir, 'themes' );
		$plugins_dir_path   = \path_join( $app_dir, 'plugins' );
		$muplugins_dir_path = \path_join( $app_dir, 'mu-plugins' );

		// If this is a path to a theme directory or file,
		// reduce it to the theme directory and find other data for it.
		if ( 0 === strpos( $path, $themes_dir_path ) ) {
			if ( array_key_exists( 'themes', $details ) ) {
				// Set the module type.
				$module['type'] = 'theme';
				// Reduce the path to just one level bellow the 'themes' directory path.
				$temp_path = trim( substr( $path, strlen( $themes_dir_path ) ), '/' );
				if ( false !== strpos( $temp_path, '/' ) ) {
					$split_path = explode( '/', $temp_path );
					if ( ! empty( $split_path[0] ) ) {
						$path = \path_join( $themes_dir_path, $split_path[0] );
					}
				}
				// Update the module path and name.
				$module['base_path'] = $path;
				$module['name']      = basename( $path );

				foreach ( $details['themes'] as $theme => $data ) {
					if ( $path === \path_join( $themes_dir_path, $theme ) ) {
						// Found the theme we were searching for.
						$module['name']    = $data['name'];
						$module['version'] = $data['version'];
						break;
					}
				}
			}
		}
		// If this is a path to a regular plugin directory or file,
		// reduce it to the plugin directory and find other data for it.
		else if ( 0 === strpos( $path, $plugins_dir_path ) ) {
			if ( array_key_exists( 'plugins', $details ) ) {
				// Set the module type.
				$module['type'] = 'plugin';
				// Reduce the path to just one level bellow the 'plugins' directory path.
				$temp_path = trim( substr( $path, strlen( $plugins_dir_path ) ), '/' );
				if ( false !== strpos( $temp_path, '/' ) ) {
					$split_path = explode( '/', $temp_path );
					if ( ! empty( $split_path[0] ) ) {
						$path = \path_join( $plugins_dir_path, $split_path[0] );
					}
				}
				// Update the module path and name.
				$module['base_path'] = $path;
				$module['name']      = basename( $path );

				foreach ( $details['plugins'] as $plugin => $data ) {
					if ( ( '.' === dirname( $plugin ) && $path === \path_join( $plugins_dir_path, $plugin ) )
					     || ( $path === \path_join( $plugins_dir_path, dirname( $plugin ) ) ) ) {
						$module['name']    = $data['name'];
						$module['version'] = $data['version'];
						break;
					}
				}
			}
		}
		// If this is a path to a must-use plugin directory or file,
		// reduce it to the plugin directory and find other data for it.
		else if ( 0 === strpos( $path, $muplugins_dir_path ) ) {
			if ( array_key_exists( 'mu-plugins', $details ) ) {
				// Set the module type.
				$module['type'] = 'mu-plugin';
				// Reduce the path to just one level bellow the 'plugins' directory path.
				$temp_path = trim( substr( $path, strlen( $muplugins_dir_path ) ), '/' );
				if ( false !== strpos( $temp_path, '/' ) ) {
					$split_path = explode( '/', $temp_path );
					if ( ! empty( $split_path[0] ) ) {
						$path = \path_join( $muplugins_dir_path, $split_path[0] );
					}
				}
				// Update the module path and name.
				$module['base_path'] = $path;
				$module['name']      = basename( $path );

				foreach ( $details['mu-plugins'] as $plugin => $data ) {
					if ( ( '.' === dirname( $plugin ) && $path === \path_join( $muplugins_dir_path, $plugin ) )
					     || ( $path === \path_join( $muplugins_dir_path, dirname( $plugin ) ) ) ) {
						$module['name']    = $data['name'];
						$module['version'] = $data['version'];
						break;
					}
				}
			}
		}

		return $module;
	}

	/**
	 * Get the git root relative path of the directory where plugins, themes are stored.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	protected function get_relative_app_dir_path(): string {
		// Determine the partial path to the directory holding plugins and themes (the regular 'wp-content', but we may not use that).
		// That is why we rely on the path of this plugin to work our way up.
		// We go two levels up: one for the plugin directory (aka 'pressody-conductor') and one for 'plugins' or 'mu-plugins'.
		$app_dir = dirname( $this->plugin->get_directory(), 2 );
		// Make it relative to the repo path.
		$app_dir = trim( str_replace( $this->git_client->get_git_repo_path(), '', $app_dir ), '/' );

		return $app_dir;
	}

	/**
	 * If the path is an absolute path to somewhere in the Git repo handled by the Git client,
	 * make the path relative to the Git repo root.
	 *
	 * @since 0.10.0
	 *
	 * @param string $path
	 *
	 * @return string The Git root relative path if the path points to somewhere within it or the unchanged path.
	 */
	protected function make_path_relative_to_git_root( string $path ): string {
		return trim( str_replace( $this->git_client->get_git_repo_path(), '', $path ), '/' );
	}

	/**
	 * Get the cached plugins and themes details list.
	 *
	 * @since 0.10.0
	 *
	 * @return array|mixed
	 */
	protected function get_plugin_and_theme_details() {
		$versions = \get_transient( self::DETAILS_TRANSIENT );
		if ( empty( $versions ) ) {
			$versions = $this->refresh_plugin_and_theme_details_cache();
		}

		return $versions;
	}

	/**
	 * Refresh the cached plugins and themes details list.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function refresh_plugin_and_theme_details_cache(): array {
		$new_data = [];

		// Get all themes.
		$all_themes = \wp_get_themes( array( 'allowed' => true ) );
		foreach ( $all_themes as $theme_name => $theme ) {
			$themes_data[ $theme_name ]        = array(
				'name'    => $theme->get( 'Name' ),
				'version' => null,
				'msg'     => '',
			);
			$themes_data[ $theme_name ]['msg'] = '`' . $theme->get( 'Name' ) . '`';
			$version                           = $theme->get( 'Version' );
			if ( ! empty( $version ) ) {
				$themes_data[ $theme_name ]['msg']     .= " version $version";
				$themes_data[ $theme_name ]['version'] .= $version;
			}
		}

		if ( ! empty( $themes_data ) ) {
			$new_data['themes'] = $themes_data;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get all regular plugins.
		$all_plugins = \get_plugins();
		foreach ( $all_plugins as $name => $data ) {
			$plugins_data[ $name ]        = array(
				'name'    => $data['Name'],
				'version' => null,
				'msg'     => '',
			);
			$plugins_data[ $name ]['msg'] = "`{$data['Name']}`";
			if ( ! empty( $data['Version'] ) ) {
				$plugins_data[ $name ]['msg']     .= ' version ' . $data['Version'];
				$plugins_data[ $name ]['version'] .= $data['Version'];
			}
		}

		if ( ! empty( $plugins_data ) ) {
			$new_data['plugins'] = $plugins_data;
		}

		// Get all must-use plugins.
		$all_mu_plugins = \get_mu_plugins();
		foreach ( $all_mu_plugins as $name => $data ) {
			$muplugins_data[ $name ]        = array(
				'name'    => $data['Name'],
				'version' => null,
				'msg'     => '',
			);
			$muplugins_data[ $name ]['msg'] = "`{$data['Name']}`";
			if ( ! empty( $data['Version'] ) ) {
				$muplugins_data[ $name ]['msg']     .= ' version ' . $data['Version'];
				$muplugins_data[ $name ]['version'] .= $data['Version'];
			}
		}

		if ( ! empty( $muplugins_data ) ) {
			$new_data['mu-plugins'] = $muplugins_data;
		}

		\set_transient( self::DETAILS_TRANSIENT, $new_data, 3 * DAY_IN_SECONDS );

		return $new_data;
	}

	/**
	 * Commit the .gitignore file and push.
	 *
	 * @since 0.10.0
	 *
	 * @param string $msg_append
	 *
	 * @return bool
	 */
	public function commit_and_push_gitignore_file( string $msg_append = '' ): bool {
		$message = 'Update `.gitignore` file';
		if ( ! empty( $msg_append ) ) {
			$msg_append = " - $msg_append";
		}

		// We will commit now and schedule the expensive merge and push.
		$commit = $this->git_client->commit_changes( $message . $msg_append, '.gitignore' );
		if ( false !== $commit ) {
			$this->schedule_git_merge_and_push( $commit );
		}

		return ( false !== $commit );
	}
}
