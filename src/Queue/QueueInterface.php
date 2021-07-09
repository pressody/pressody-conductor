<?php
/**
 * Queue Interface
 *
 * Borrowed from WooCommerce.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Queue;

/**
 * Queue Interface
 *
 * Functions that must be defined to implement an action/job/event queue.
 *
 * @version 0.1.0
 */
interface QueueInterface {

	/**
	 * Enqueue an action to run one time, as soon as possible
	 *
	 * @param string $hook  The hook to trigger.
	 * @param array  $args  Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 *
	 * @return int The action ID
	 */
	public function add( string $hook, array $args = [], string $group = '' ): int;

	/**
	 * Schedule an action to run once at some time in the future
	 *
	 * @param int    $timestamp When the job will run.
	 * @param string $hook The hook to trigger.
	 * @param array  $args Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 * @return int The action ID
	 */
	public function schedule_single( int $timestamp, string $hook, array $args = [], string $group = '' ): int;

	/**
	 * Schedule a recurring action
	 *
	 * @param int    $timestamp When the first instance of the job will run.
	 * @param int    $interval_in_seconds How long to wait between runs.
	 * @param string $hook The hook to trigger.
	 * @param array  $args Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 * @return int The action ID
	 */
	public function schedule_recurring( int $timestamp, int $interval_in_seconds, string $hook, array $args = array(), string $group = '' ): int;

	/**
	 * Schedule an action that recurs on a cron-like schedule.
	 *
	 * @param int    $timestamp The schedule will start on or after this time.
	 * @param string $cron_schedule A cron-link schedule string.
	 * @see http://en.wikipedia.org/wiki/Cron
	 *   *    *    *    *    *    *
	 *   ┬    ┬    ┬    ┬    ┬    ┬
	 *   |    |    |    |    |    |
	 *   |    |    |    |    |    + year [optional]
	 *   |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
	 *   |    |    |    +---------- month (1 - 12)
	 *   |    |    +--------------- day of month (1 - 31)
	 *   |    +-------------------- hour (0 - 23)
	 *   +------------------------- min (0 - 59)
	 * @param string $hook The hook to trigger.
	 * @param array  $args Arguments to pass when the hook triggers.
	 * @param string $group The group to assign this job to.
	 * @return int The action ID
	 */
	public function schedule_cron( int $timestamp, string $cron_schedule, string $hook, array $args = array(), string $group = '' ): int;

	/**
	 * Dequeue the next scheduled instance of an action with a matching hook (and optionally matching args and group).
	 *
	 * Any recurring actions with a matching hook should also be cancelled, not just the next scheduled action.
	 *
	 * @param string $hook The hook that the job will trigger.
	 * @param array  $args Args that would have been passed to the job.
	 * @param string $group The group the job is assigned to (if any).
	 */
	public function cancel( string $hook, array $args = array(), string $group = '' );

	/**
	 * Dequeue all actions with a matching hook (and optionally matching args and group) so no matching actions are ever run.
	 *
	 * @param string $hook The hook that the job will trigger.
	 * @param array  $args Args that would have been passed to the job.
	 * @param string $group The group the job is assigned to (if any).
	 */
	public function cancel_all( string $hook, array $args = array(), string $group = '' );

	/**
	 * Get the date and time for the next scheduled occurrence of an action with a given hook
	 * (an optionally that matches certain args and group), if any.
	 *
	 * @param string     $hook  The hook that the job will trigger.
	 * @param array|null $args  Filter to a hook with matching args that will be passed to the job when it runs.
	 * @param string     $group Filter to only actions assigned to a specific group.
	 *
	 * @return \DateTime|null The date and time for the next occurrence, or null if there is no pending, scheduled action for the given hook.
	 */
	public function get_next( string $hook, array $args = null, string $group = '' ): ?\DateTime;

	/**
	 * Find scheduled actions.
	 *
	 * @param array  $args Possible arguments, with their default values.
	 *        'hook' => '' - the name of the action that will be triggered.
	 *        'args' => null - the args array that will be passed with the action.
	 *        'date' => null - the scheduled date of the action. Expects a DateTime object, a unix timestamp, or a string that can parsed with strtotime(). Used in UTC timezone.
	 *        'date_compare' => '<=' - operator for testing "date". accepted values are '!=', '>', '>=', '<', '<=', '='.
	 *        'modified' => null - the date the action was last updated. Expects a DateTime object, a unix timestamp, or a string that can parsed with strtotime(). Used in UTC timezone.
	 *        'modified_compare' => '<=' - operator for testing "modified". accepted values are '!=', '>', '>=', '<', '<=', '='.
	 *        'group' => '' - the group the action belongs to.
	 *        'status' => '' - ActionScheduler_Store::STATUS_COMPLETE or ActionScheduler_Store::STATUS_PENDING.
	 *        'claimed' => null - TRUE to find claimed actions, FALSE to find unclaimed actions, a string to find a specific claim ID.
	 *        'per_page' => 5 - Number of results to return.
	 *        'offset' => 0.
	 *        'orderby' => 'date' - accepted values are 'hook', 'group', 'modified', or 'date'.
	 *        'order' => 'ASC'.
	 * @param string $return_format OBJECT, ARRAY_A, or ids.
	 * @return array
	 */
	public function search( array $args = array(), string $return_format = OBJECT ): array;
}
