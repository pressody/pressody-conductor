<?php
/**
 * Class DBLogHandler file.
 *
 * Code borrowed and modified from WooCommerce.
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

namespace Pressody\Conductor\Logging\Handler;

use Automattic\Jetpack\Constants;
use Pressody\Conductor\Logging\LogLevels;

/**
 * Handles log entries by writing to database.
 *
 * @since 0.1.0
 */
class DBLogHandler extends LogHandler {

	/**
	 * Handle a log entry.
	 *
	 * @see DBLogHandler::get_log_source() for default source.
	 *
	 * @param string    $level     emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string    $message   Log message.
	 * @param array     $context   {
	 *      Additional information for log handlers.
	 *
	 *     @type string $source    Optional. Source will be available in log table.
	 *                  If no source is provided, attempt to provide sensible default.
	 * }
	 *
	 * @param int       $timestamp Log timestamp.
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( int $timestamp, string $level, string $message, array $context ): bool {

		if ( isset( $context['source'] ) && $context['source'] ) {
			$source = $context['source'];
		} else {
			$source = $this->get_log_source();
		}

		return $this->add( $timestamp, $level, $message, $source, $context );
	}

	/**
	 * Add a log entry to chosen file.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level     emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message   Log message.
	 * @param string $source    Log source. Useful for filtering and sorting.
	 * @param array  $context   Context will be serialized and stored in database.
	 *
	 * @return bool True if write was successful.
	 */
	protected static function add( int $timestamp, string $level, string $message, string $source, array $context ): bool {
		global $wpdb;

		$insert = [
			'timestamp' => date( 'Y-m-d H:i:s', $timestamp ),
			'level'     => LogLevels::get_level_severity( $level ),
			'message'   => $message,
			'source'    => $source,
		];

		$format = [
			'%s',
			'%d',
			'%s',
			'%s',
			'%s', // possible serialized context.
		];

		if ( ! empty( $context ) ) {
			$insert['context'] = serialize( $context ); // @codingStandardsIgnoreLine.
		}

		return false !== $wpdb->insert( "{$wpdb->prefix}pressody_conductor_log", $insert, $format );
	}

	/**
	 * Clear all logs from the DB.
	 *
	 * @return bool True if flush was successful.
	 */
	public static function flush(): bool {
		global $wpdb;

		return $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}pressody_conductor_log" );
	}

	/**
	 * Clear entries for a chosen handle/source.
	 *
	 * @param string $source Log source.
	 *
	 * @return bool
	 */
	public function clear( string $source ): bool {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}pressody_conductor_log WHERE source = %s",
				$source
			)
		);
	}

	/**
	 * Delete selected logs from DB.
	 *
	 * @param int|string|array $log_ids Log ID or array of Log IDs to be deleted.
	 *
	 * @return bool
	 */
	public static function delete( $log_ids ): bool {
		global $wpdb;

		if ( ! is_array( $log_ids ) ) {
			$log_ids = array( $log_ids );
		}

		$format   = array_fill( 0, count( $log_ids ), '%d' );
		$query_in = '(' . implode( ',', $format ) . ')';
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}pressody_conductor_log WHERE log_id IN {$query_in}", $log_ids ) ); // @codingStandardsIgnoreLine.
	}

	/**
	 * Delete all logs older than a defined timestamp.
	 *
	 * @param integer $timestamp Timestamp to delete logs before.
	 */
	public static function delete_logs_before_timestamp( $timestamp = 0 ) {
		if ( ! $timestamp ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}pressody_conductor_log WHERE timestamp < %s",
				date( 'Y-m-d H:i:s', $timestamp )
			)
		);
	}

	/**
	 * Get appropriate source based on file name.
	 *
	 * Try to provide an appropriate source in case none is provided.
	 *
	 * @return string Text to use as log source. "" (empty string) if none is found.
	 */
	protected static function get_log_source(): string {
		static $ignore_files = array( 'DBLogHandler', 'Logger' );

		/**
		 * PHP < 5.3.6 correct behavior
		 *
		 * @see http://php.net/manual/en/function.debug-backtrace.php#refsect1-function.debug-backtrace-parameters
		 */
		if ( Constants::is_defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) ) {
			$debug_backtrace_arg = DEBUG_BACKTRACE_IGNORE_ARGS; // phpcs:ignore PHPCompatibility.Constants.NewConstants.debug_backtrace_ignore_argsFound
		} else {
			$debug_backtrace_arg = false;
		}

		$trace = debug_backtrace( $debug_backtrace_arg ); // @codingStandardsIgnoreLine.
		foreach ( $trace as $t ) {
			if ( isset( $t['file'] ) ) {
				$filename = pathinfo( $t['file'], PATHINFO_FILENAME );
				if ( ! in_array( $filename, $ignore_files, true ) ) {
					return $filename;
				}
			}
		}

		return '';
	}

}
