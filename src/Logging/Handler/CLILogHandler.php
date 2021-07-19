<?php
/**
 * Class CLILogHandler file.
 *
 * Code borrowed and modified from WooCommerce.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Logging\Handler;

use Exception;
use PixelgradeLT\Conductor\Utils\JSONCleaner;
use Psr\Log\LogLevel;

/**
 * Handles log entries by outputting to the CLI.
 *
 * @since 0.1.0
 */
class CLILogHandler extends LogHandler {

	/**
	 * Handle a log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level     emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message   Log message.
	 * @param array  $context   {
	 *                          Additional information for log handlers.
	 *
	 * @type string  $source    Optional. Determines log file to write to. Default 'log'.
	 * @type bool    $_legacy   Optional. Default false. True to use outdated log format
	 *         originally used in deprecated WC_Logger::add calls.
	 * }
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( int $timestamp, string $level, string $message, array $context ): bool {

		$entry = $this->format_entry( $timestamp, $level, $message, $context );

		switch ( $level ) {
			case LogLevel::EMERGENCY:
			case LogLevel::ALERT:
			case LogLevel::CRITICAL:
			case LogLevel::ERROR:
				\WP_CLI::error( $entry, false );
				break;
			case LogLevel::WARNING:
				\WP_CLI::warning( $entry );
				break;
			case LogLevel::NOTICE:
			case LogLevel::INFO:
				\WP_CLI::log( $entry );
				break;
			case LogLevel::DEBUG:
				\WP_CLI::debug( $entry );
				break;
		}

		// We want to let other log handlers do their things, since this logger is not actually logging, but displaying.
		return false;
	}

	/**
	 * Builds a log entry text from timestamp, level and message.
	 *
	 * - Interpolates context values into message placeholders.
	 * - Appends additional context data as JSON.
	 * - Appends exception data.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level     emergency|alert|critical|error|warning|notice|info|debug.
	 *                          See Psr\Log\LogLevel
	 * @param string $message   Log message.
	 * @param array  $context   Additional information for log handlers.
	 *
	 * @return string Formatted log entry.
	 */
	protected function format_entry( int $timestamp, string $level, string $message, array $context ): string {
		// Extract exceptions from the context array.
		$exception = $context['exception'] ?? null;
		unset( $context['exception'] );

		$entry = "{$message}";

		// Replace any context data provided in the message.
		$search       = [];
		$replace      = [];
		$temp_context = $context;
		foreach ( $temp_context as $key => $value ) {
			$placeholder = '{' . $key . '}';

			if ( false === strpos( $message, $placeholder ) ) {
				continue;
			}

			array_push( $search, '{' . $key . '}' );
			array_push( $replace, $this->to_string( $value ) );
			unset( $temp_context[ $key ] );
		}

		$entry = str_replace( $search, $replace, $entry );

		// Append additional context data.
		if ( isset( $temp_context['logCategory'] ) ) {
			unset( $temp_context['logCategory'] );
		}
		if ( ! empty( $temp_context ) ) {
			$entry .= PHP_EOL . PHP_EOL . '  ADDITIONAL CONTEXT: ' . json_encode( $temp_context, \JSON_PRETTY_PRINT );
		}

		// Now attach an exception, if provided.
		if ( ! empty( $exception ) && $exception instanceof Exception ) {
			$entry .= PHP_EOL . PHP_EOL . ' THROWN_EXCEPTION: ' . $this->format_exception( $exception );
		}

		return $entry;
	}

	/**
	 * Format an exception.
	 *
	 * @since 0.1.0
	 *
	 * @param Exception $e Exception.
	 *
	 * @return string
	 */
	protected function format_exception( Exception $e ): string {
		// Since the trace may contain in a step's args circular references, we need to replace such references with a string.
		// This is to avoid infinite recursion when attempting to json_encode().
		$trace             = JSONCleaner::clean( $e->getTrace(), 6 );
		$encoded_exception = print_r(
			[
				'message' => $e->getMessage(),
				'code'    => $e->getCode(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'trace'   => $trace,
			],
			true
		);

		if ( ! is_string( $encoded_exception ) ) {
			return 'failed-to-encode-exception';
		}

		return $encoded_exception;
	}
}
