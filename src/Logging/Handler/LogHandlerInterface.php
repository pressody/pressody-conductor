<?php
/**
 * Log Handler Interface
 *
 * Code borrowed and modified from WooCommerce.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Logging\Handler;

/**
 * Log Handler Interface
 *
 * Methods that must be defined to correctly fulfill log handler API.
 *
 * @since 0.1.0
 */
interface LogHandlerInterface {

	/**
	 * Handle a log entry.
	 *
	 * @param int    $timestamp Log timestamp.
	 * @param string $level     emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string $message   Log message.
	 * @param array  $context   Additional information for log handlers.
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( int $timestamp, string $level, string $message, array $context ): bool;
}
