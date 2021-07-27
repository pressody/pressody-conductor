<?php
/**
 * Cache management routines.
 *
 * @since   0.9.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Composition;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;

/**
 * Class to manage the site cache.
 *
 * @since 0.9.0
 */
class CacheManager extends AbstractHookProvider {

	/**
	 * Queue.
	 *
	 * @since 0.9.0
	 *
	 * @var QueueInterface
	 */
	protected QueueInterface $queue;

	/**
	 * Logger.
	 *
	 * @since 0.9.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.9.0
	 *
	 * @param QueueInterface           $queue  Queue.
	 * @param LoggerInterface          $logger Logger.
	 */
	public function __construct(
		QueueInterface $queue,
		LoggerInterface $logger
	) {
		$this->queue           = $queue;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.9.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_recurring_events' );

		add_action( 'pixelgradelt_conductor/clear_site_cache', [ $this, 'clear_site_cache' ] );
	}

	/**
	 * Maybe schedule the recurring actions/events, if it is not already scheduled.
	 *
	 * @since 0.9.0
	 */
	protected function schedule_recurring_events() {
		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pixelgradelt_conductor/midnight', [], 'pixelgrade-conductor' );
		}

		if ( ! $this->queue->get_next( 'pixelgradelt_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pixelgradelt_conductor/hourly', [], 'pixelgrade-conductor' );
		}
	}

	public function clear_site_cache() {
		// Do/trigger the clearing of specific site-cache parts.
	}
}
