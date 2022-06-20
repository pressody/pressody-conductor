<?php
/**
 * Standard log levels
 *
 * Code borrowed and modified from WooCommerce.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\Logging;

use Psr\Log\LogLevel;

/**
 * Log levels class.
 */
abstract class LogLevels {

	/**
	 * Log Levels
	 *
	 * Description of levels:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 *
	 * @see @link {https://tools.ietf.org/html/rfc5424}
	 */

	/**
	 * Level strings mapped to integer severity.
	 *
	 * @var array
	 */
	protected static array $level_to_severity = [
		LogLevel::EMERGENCY => 800,
		LogLevel::ALERT     => 700,
		LogLevel::CRITICAL  => 600,
		LogLevel::ERROR     => 500,
		LogLevel::WARNING   => 400,
		LogLevel::NOTICE    => 300,
		LogLevel::INFO      => 200,
		LogLevel::DEBUG     => 100,
	];

	/**
	 * Severity integers mapped to level strings.
	 *
	 * This is the inverse of $level_severity.
	 *
	 * @var array
	 */
	protected static array $severity_to_level = [
		800 => LogLevel::EMERGENCY,
		700 => LogLevel::ALERT,
		600 => LogLevel::CRITICAL,
		500 => LogLevel::ERROR,
		400 => LogLevel::WARNING,
		300 => LogLevel::NOTICE,
		200 => LogLevel::INFO,
		100 => LogLevel::DEBUG,
	];


	/**
	 * Validate a level string.
	 *
	 * @param string $level Log level.
	 *
	 * @return bool True if $level is a valid level.
	 */
	public static function is_valid_level( string $level ): bool {
		return array_key_exists( strtolower( $level ), self::$level_to_severity );
	}

	/**
	 * Translate level string to integer.
	 *
	 * @param string $level Log level, options: emergency|alert|critical|error|warning|notice|info|debug.
	 *
	 * @return int 100 (debug) - 800 (emergency) or 0 if not recognized
	 */
	public static function get_level_severity( string $level ): int {
		return self::is_valid_level( $level ) ? self::$level_to_severity[ strtolower( $level ) ] : 0;
	}

	/**
	 * Translate severity integer to level string.
	 *
	 * @param int $severity Severity level.
	 *
	 * @return bool|string False if not recognized. Otherwise string representation of level.
	 */
	public static function get_severity_level( int $severity ) {
		if ( ! array_key_exists( $severity, self::$severity_to_level ) ) {
			return false;
		}
		return self::$severity_to_level[ $severity ];
	}

}
