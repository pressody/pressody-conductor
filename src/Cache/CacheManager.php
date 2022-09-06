<?php
/**
 * Cache management routines.
 *
 * @since   0.9.0
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

namespace Pressody\Conductor\Cache;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Queue\QueueInterface;
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
	 * @param QueueInterface  $queue  Queue.
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		QueueInterface $queue,
		LoggerInterface $logger
	) {
		$this->queue  = $queue;
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.9.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_recurring_events' );

		add_action( 'pressody_conductor/clear_site_cache', [ $this, 'clear_site_cache' ] );
	}

	/**
	 * Maybe schedule the recurring actions/events, if it is not already scheduled.
	 *
	 * @since 0.9.0
	 */
	protected function schedule_recurring_events() {
		if ( ! $this->queue->get_next( 'pressody_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pressody_conductor/midnight', [], 'plt_con' );
		}

		if ( ! $this->queue->get_next( 'pressody_conductor/hourly' ) ) {
			$this->queue->schedule_recurring( (int) floor( ( time() + HOUR_IN_SECONDS ) / HOUR_IN_SECONDS ), HOUR_IN_SECONDS, 'pressody_conductor/hourly', [], 'plt_con' );
		}
	}

	public function clear_site_cache() {
		// Do/trigger the clearing of specific site-cache parts.
	}
}
