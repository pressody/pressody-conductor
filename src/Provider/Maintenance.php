<?php
/**
 * Maintenance routines.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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
