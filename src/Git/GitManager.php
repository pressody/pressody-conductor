<?php
/**
 * Git management routines.
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

/**
 * Class to manage the Git integration of the site.
 *
 * @since 0.10.0
 */
class GitManager extends AbstractHookProvider {

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
	 * @param CompositionManager $composition_manager The composition manager.
	 * @param QueueInterface     $queue               Queue.
	 * @param LoggerInterface    $logger              Logger.
	 */
	public function __construct(
		CompositionManager $composition_manager,
		QueueInterface $queue,
		LoggerInterface $logger
	) {
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

		add_action( 'update_option_' . $this->composition_manager::COMPOSITION_PLUGINS_OPTION_NAME, [ $this, 'gitignore_update_composer_plugins' ] );
		add_action( 'update_option_' . $this->composition_manager::COMPOSITION_THEMES_OPTION_NAME, [ $this, 'gitignore_update_composer_themes' ] );
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
	 * Get the absolute path to the site's .gitignore file.
	 *
	 * @since 0.10.0
	 * @return string
	 */
	protected function get_gitignore_path(): string {
		return \path_join( \LT_ROOT_DIR, '.gitignore' );
	}

	/**
	 * Get the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 * @return string|false The contents or false on failure.
	 */
	protected function read_gitignore() {
		return file_get_contents( $this->get_gitignore_path() );
	}

	/**
	 * Write the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 * @return bool True on success or false on failure.
	 */
	protected function write_gitignore( string $contents ): bool {
		return false !== file_put_contents( $this->get_gitignore_path(), $contents );
	}

	/**
	 * Update the site's .gitignore file with the plugins installed by Composer.
	 *
	 * This way we only ignore what we install, leaving the user to manually install things.
	 *
	 * @since 0.10.0
	 * @return bool
	 */
	public function gitignore_update_composer_plugins(): bool {
		$contents = $this->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_plugins_dir_path = ltrim( str_replace( LT_ROOT_DIR, '', WP_PLUGIN_DIR ), '/' );

		$plugins = $this->composition_manager->get_composition_plugin();
		$ignore_list = array_keys( $plugins );
		$ignore_list = array_map( function ( $ignore ) use ( $relative_plugins_dir_path ) {
			return dirname( \path_join( $relative_plugins_dir_path, $ignore ) );
		}, $ignore_list );

		$contents = preg_replace(
			'/(# composer_wp_plugins_start #)(\r\n|\r|\n).*(# composer_wp_plugins_end #)/is',
			'$1$2' . implode( PHP_EOL, $ignore_list ) . (! empty($ignore_list) ? PHP_EOL : '') . '$3',
			$contents
		);

		return $this->write_gitignore( $contents );
	}

	/**
	 * Update the site's .gitignore file with the themes installed by Composer.
	 *
	 * This way we only ignore what we install, leaving the user to manually install things.
	 *
	 * @since 0.10.0
	 * @return bool
	 */
	public function gitignore_update_composer_themes(): bool {
		$contents = $this->read_gitignore();
		if ( false === $contents ) {
			return false;
		}

		$relative_themes_dir_path = ltrim( str_replace( LT_ROOT_DIR, '', WP_CONTENT_DIR . '/themes' ), '/' );

		$themes = $this->composition_manager->get_composition_theme();
		$ignore_list = array_keys( $themes );
		$ignore_list = array_map( function ( $ignore ) use ( $relative_themes_dir_path ) {
			return \path_join( $relative_themes_dir_path, $ignore );
		}, $ignore_list );

		$contents = preg_replace(
			'/(# composer_wp_themes_start #)(\r\n|\r|\n).*(# composer_wp_themes_end #)/is',
			'$1$2' . implode( PHP_EOL, $ignore_list ) . (! empty($ignore_list) ? PHP_EOL : '') . '$3',
			$contents
		);

		return $this->write_gitignore( $contents );
	}
}
