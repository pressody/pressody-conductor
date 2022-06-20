<?php
/**
 * Maintenance routines.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Class to do maintenance.
 *
 * @since 0.1.0
 */
class Maintenance extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_filter('action_scheduler_retention_period', 'adjust_action_scheduler_logs_retention_period' );
	}

	/**
	 * Action Scheduler retains logs for 31 days, by default. We want less retention to avoid DB hammering.
	 *
	 * @param int $period_in_seconds
	 *
	 * @return int
	 */
	protected function adjust_action_scheduler_logs_retention_period( int $period_in_seconds ): int {
		$period_in_seconds = \DAY_IN_SECONDS * 15;

		return $period_in_seconds;
	}
}
