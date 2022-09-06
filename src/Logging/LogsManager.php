<?php
/**
 * Logs management routines.
 *
 * @since   0.1.0
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

namespace Pressody\Conductor\Logging;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Queue\QueueInterface;
use Psr\Log\LoggerInterface;

/**
 * Class to manage logs.
 *
 * @since 0.1.0
 */
class LogsManager extends AbstractHookProvider {

	/**
	 * Logger.
	 *
	 * @since 0.1.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Queue.
	 *
	 * @since 0.1.0
	 *
	 * @var QueueInterface
	 */
	protected QueueInterface $queue;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param LoggerInterface $logger Logger.
	 * @param QueueInterface  $queue  Queue.
	 */
	public function __construct(
		LoggerInterface $logger,
		QueueInterface $queue
	) {
		$this->logger = $logger;
		$this->queue  = $queue;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'schedule_cleanup_logs_event' );
		$this->add_action( 'pressody_conductor/midnight', 'cleanup_logs' );
	}

	/**
	 * Maybe schedule the action/event to run logs cleanup at, if it is not already scheduled.
	 *
	 * @since 0.1.0
	 */
	protected function schedule_cleanup_logs_event() {
		if ( ! $this->queue->get_next( 'pressody_conductor/midnight' ) ) {
			$this->queue->schedule_recurring( strtotime( 'tomorrow' ), DAY_IN_SECONDS, 'pressody_conductor/midnight', [], 'plt_con' );
		}
	}

	/**
	 * Calls the logger's clean expired logs logic.
	 *
	 * @since 0.1.0
	 */
	protected function cleanup_logs() {
		if ( is_callable( array( $this->logger, 'clear_expired_logs' ) ) ) {
			$this->logger->clear_expired_logs();
		}
	}
}
