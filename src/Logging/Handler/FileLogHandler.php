<?php
/**
 * Class FileLogHandler file.
 *
 * Code borrowed and modified from WooCommerce.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\Logging\Handler;

use Automattic\Jetpack\Constants;
use Exception;
use Pressody\Conductor\Utils\JSONCleaner;
use function Pressody\Conductor\doing_it_wrong;
use const Pressody\Conductor\LOG_DIR;

/**
 * Handles log entries by writing to a file.
 *
 * @since 0.1.0
 */
class FileLogHandler extends LogHandler {

	/**
	 * Stores open file handles.
	 *
	 * @var array
	 */
	protected array $handles = [];

	/**
	 * File size limit for log files in bytes.
	 *
	 * @var int
	 */
	protected int $log_size_limit;

	/**
	 * Cache logs that could not be written.
	 *
	 * If a log is written too early in the request, pluggable functions may be unavailable. These
	 * logs will be cached and written on 'plugins_loaded' action.
	 *
	 * @var array
	 */
	protected array $cached_logs = [];

	/**
	 * Constructor for the logger.
	 *
	 * @param int|null $log_size_limit Optional. Size limit for log files. Default 5mb.
	 */
	public function __construct( int $log_size_limit = null ) {
		if ( null === $log_size_limit ) {
			$log_size_limit = 5 * 1024 * 1024;
		}

		/** @noinspection \PhpFieldAssignmentTypeMismatchInspection */
		$this->log_size_limit = apply_filters( 'pressody_conductor/log_file_size_limit', $log_size_limit );

		add_action( 'plugins_loaded', array( $this, 'write_cached_logs' ) );
	}

	/**
	 * Destructor.
	 *
	 * Cleans up open file handles.
	 */
	public function __destruct() {
		foreach ( $this->handles as $handle ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle ); // @codingStandardsIgnoreLine.
			}
		}
	}

	/**
	 * Handle a log entry.
	 *
	 * @param int       $timestamp Log timestamp.
	 * @param string    $level     emergency|alert|critical|error|warning|notice|info|debug.
	 * @param string    $message   Log message.
	 * @param array     $context   {
	 *      Additional information for log handlers.
	 *
	 *     @type string $source    Optional. Determines log file to write to. Default 'log'.
	 *     @type bool   $_legacy   Optional. Default false. True to use outdated log format
	 *         originally used in deprecated WC_Logger::add calls.
	 * }
	 *
	 * @return bool False if value was not handled and true if value was handled.
	 */
	public function handle( int $timestamp, string $level, string $message, array $context ): bool {

		if ( isset( $context['source'] ) && $context['source'] ) {
			$handle = $context['source'];
		} else {
			$handle = 'log';
		}

		$entry = $this->format_entry( $timestamp, $level, $message, $context );

		return $this->add( $entry, $handle );
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
	 * @param string $message   Log message.
	 * @param array  $context   Additional information for log handlers.
	 *
	 * @return string Formatted log entry.
	 */
	protected function format_entry( int $timestamp, string $level, string $message, array $context ): string {
		// Extract exceptions from the context array.
		$exception = $context['exception'] ?? null;
		unset( $context['exception'] );

		// First, let the general logic generate the entry.
		$entry = parent::format_entry( $timestamp, $level, $message, $context );

		// Now attach an exception, if provided.
		if ( ! empty( $exception ) && $exception instanceof Exception ) {
			$entry .= ' THROWN_EXCEPTION: ' . $this->format_exception( $exception );
		}

		return $entry;
	}

	/**
	 * Format an exception.
	 *
	 * @since 0.1.0
	 *
	 * @param Exception $e Exception.
	 * @return string
	 */
	protected function format_exception( Exception $e ): string {
		// Since the trace may contain in a step's args circular references, we need to replace such references with a string.
		// This is to avoid infinite recursion when attempting to json_encode().
		$trace = JSONCleaner::clean( $e->getTrace(), 6 );
		$encoded_exception = wp_json_encode(
			[
				'message' => $e->getMessage(),
				'code'    => $e->getCode(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'trace'   => $trace,
			],
			\JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $encoded_exception ) ) {
			return 'failed-to-encode-exception';
		}

		return $encoded_exception;
	}

	/**
	 * Open log file for writing.
	 *
	 * @param string $handle Log handle.
	 * @param string $mode   Optional. File mode. Default 'a'.
	 *
	 * @return bool Success.
	 */
	protected function open( string $handle, string $mode = 'a' ): bool {
		if ( $this->is_open( $handle ) ) {
			return true;
		}

		$file = self::get_log_file_path( $handle );

		if ( $file ) {
			if ( ! file_exists( $file ) ) {
				if ( ! wp_mkdir_p( \dirname( $file ) ) ) {
					return false;
				}

				$temphandle = @fopen( $file, 'w+' ); // @codingStandardsIgnoreLine.
				@fclose( $temphandle ); // @codingStandardsIgnoreLine.

				if ( Constants::is_defined( 'FS_CHMOD_FILE' ) ) {
					@chmod( $file, FS_CHMOD_FILE ); // @codingStandardsIgnoreLine.
				}
			}

			$resource = @fopen( $file, $mode ); // @codingStandardsIgnoreLine.

			if ( $resource ) {
				$this->handles[ $handle ] = $resource;
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a handle is open.
	 *
	 * @param string $handle Log handle.
	 *
	 * @return bool True if $handle is open.
	 */
	protected function is_open( string $handle ): bool {
		return array_key_exists( $handle, $this->handles ) && is_resource( $this->handles[ $handle ] );
	}

	/**
	 * Close a handle.
	 *
	 * @param string $handle Log handle.
	 *
	 * @return bool success
	 */
	protected function close( string $handle ): bool {
		$result = false;

		if ( $this->is_open( $handle ) ) {
			$result = fclose( $this->handles[ $handle ] ); // @codingStandardsIgnoreLine.
			unset( $this->handles[ $handle ] );
		}

		return $result;
	}

	/**
	 * Add a log entry to chosen file.
	 *
	 * @param string $entry  Log entry text.
	 * @param string $handle Log entry handle.
	 *
	 * @return bool True if write was successful.
	 */
	protected function add( string $entry, string $handle ): bool {
		$result = false;

		if ( $this->should_rotate( $handle ) ) {
			$this->log_rotate( $handle );
		}

		if ( $this->open( $handle ) && is_resource( $this->handles[ $handle ] ) ) {
			$result = fwrite( $this->handles[ $handle ], $entry . PHP_EOL ); // @codingStandardsIgnoreLine.
		} else {
			$this->cache_log( $entry, $handle );
		}

		return false !== $result;
	}

	/**
	 * Clear entries from chosen file.
	 *
	 * @param string $handle Log handle.
	 *
	 * @return bool
	 */
	public function clear( string $handle ): bool {
		$result = false;

		// Close the file if it's already open.
		$this->close( $handle );

		/**
		 * $this->open( $handle, 'w' ) == Open the file for writing only. Place the file pointer at
		 * the beginning of the file, and truncate the file to zero length.
		 */
		if ( $this->open( $handle, 'w' ) && is_resource( $this->handles[ $handle ] ) ) {
			$result = true;
		}

		do_action( 'pressody_conductor/log_clear', $handle );

		return $result;
	}

	/**
	 * Remove/delete the chosen file.
	 *
	 * @param string $handle Log handle.
	 *
	 * @return bool
	 */
	public function remove( string $handle ): bool {
		$removed = false;
		$logs    = $this->get_log_files();
		$handle  = sanitize_title( $handle );

		if ( isset( $logs[ $handle ] ) && $logs[ $handle ] ) {
			$file = realpath( trailingslashit( LOG_DIR ) . $logs[ $handle ] );
			if ( 0 === stripos( $file, realpath( trailingslashit( LOG_DIR ) ) ) && is_file( $file ) && is_writable( $file ) ) { // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_is_writable
				$this->close( $file ); // Close first to be certain no processes keep it alive after it is unlinked.
				$removed = unlink( $file ); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_unlink
			}
			do_action( 'pressody_conductor/log_remove', $handle, $removed );
		}
		return $removed;
	}

	/**
	 * Check if log file should be rotated.
	 *
	 * Compares the size of the log file to determine whether it is over the size limit.
	 *
	 * @param string $handle Log handle.
	 *
	 * @return bool True if if should be rotated.
	 */
	protected function should_rotate( string $handle ): bool {
		$file = self::get_log_file_path( $handle );
		if ( $file ) {
			if ( $this->is_open( $handle ) ) {
				$file_stat = fstat( $this->handles[ $handle ] );
				return $file_stat['size'] > $this->log_size_limit;
			} elseif ( file_exists( $file ) ) {
				return filesize( $file ) > $this->log_size_limit;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Rotate log files.
	 *
	 * Logs are rotated by prepending '.x' to the '.log' suffix.
	 * The current log plus 10 historical logs are maintained.
	 * For example:
	 *     base.9.log -> [ REMOVED ]
	 *     base.8.log -> base.9.log
	 *     ...
	 *     base.0.log -> base.1.log
	 *     base.log   -> base.0.log
	 *
	 * @param string $handle Log handle.
	 */
	protected function log_rotate( string $handle ) {
		for ( $i = 8; $i >= 0; $i-- ) {
			$this->increment_log_infix( $handle, $i );
		}
		$this->increment_log_infix( $handle );
	}

	/**
	 * Increment a log file suffix.
	 *
	 * @param string   $handle Log handle.
	 * @param null|int $number Optional. Default null. Log suffix number to be incremented.
	 *
	 * @return bool True if increment was successful, otherwise false.
	 */
	protected function increment_log_infix( string $handle, $number = null ): bool {
		if ( null === $number ) {
			$suffix      = '';
			$next_suffix = '.0';
		} else {
			$suffix      = '.' . $number;
			$next_suffix = '.' . ( $number + 1 );
		}

		$rename_from = self::get_log_file_path( "{$handle}{$suffix}" );
		$rename_to   = self::get_log_file_path( "{$handle}{$next_suffix}" );

		if ( $this->is_open( $rename_from ) ) {
			$this->close( $rename_from );
		}

		if ( is_writable( $rename_from ) ) { // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_is_writable
			return rename( $rename_from, $rename_to ); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_rename
		} else {
			return false;
		}

	}

	/**
	 * Get a log file path.
	 *
	 * @param string $handle Log name.
	 *
	 * @return bool|string The log file path or false if path cannot be determined.
	 */
	public static function get_log_file_path( string $handle ) {
		if ( function_exists( 'wp_hash' ) ) {
			return trailingslashit( LOG_DIR ) . self::get_log_file_name( $handle );
		} else {
			doing_it_wrong( __METHOD__, __( 'This method should not be called before plugins_loaded.', 'pressody_conductor' ), '0.1.0' );
			return false;
		}
	}

	/**
	 * Get a log file name.
	 *
	 * File names consist of the handle, followed by the date, followed by a hash, .log.
	 *
	 * @since 0.1.0
	 *
	 * @param string $handle Log name.
	 *
	 * @return bool|string The log file name or false if cannot be determined.
	 */
	public static function get_log_file_name( string $handle ) {
		if ( function_exists( 'wp_hash' ) ) {
			$date_suffix = date( 'Y-m-d', time() );
			$hash_suffix = wp_hash( $handle );
			return sanitize_file_name( implode( '-', [ $handle, $date_suffix, $hash_suffix ] ) . '.log' );
		} else {
			doing_it_wrong( __METHOD__, __( 'This method should not be called before plugins_loaded.', 'pressody_conductor' ), '0.9.0' );
			return false;
		}
	}

	/**
	 * Cache log to write later.
	 *
	 * @param string $entry  Log entry text.
	 * @param string $handle Log entry handle.
	 */
	protected function cache_log( string $entry, string $handle ) {
		$this->cached_logs[] = [
			'entry'  => $entry,
			'handle' => $handle,
		];
	}

	/**
	 * Write cached logs.
	 */
	public function write_cached_logs() {
		foreach ( $this->cached_logs as $log ) {
			$this->add( $log['entry'], $log['handle'] );
		}
	}

	/**
	 * Delete all logs older than a defined timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @param int $timestamp Timestamp to delete logs before.
	 */
	public static function delete_logs_before_timestamp( int $timestamp = 0 ) {
		if ( ! $timestamp ) {
			return;
		}

		$log_files = self::get_log_files();

		foreach ( $log_files as $log_file ) {
			$last_modified = filemtime( trailingslashit( LOG_DIR ) . $log_file );

			if ( $last_modified < $timestamp ) {
				@unlink( trailingslashit( LOG_DIR ) . $log_file ); // @codingStandardsIgnoreLine.
			}
		}
	}

	/**
	 * Get all log files in the log directory.
	 *
	 * @since 0.1.0
	 * @return array
	 */
	public static function get_log_files(): array {
		$files  = @scandir( LOG_DIR ); // @codingStandardsIgnoreLine.
		$result = [];

		if ( ! empty( $files ) ) {
			foreach ( $files as $key => $value ) {
				if ( ! in_array( $value, [ '.', '..', ], true ) ) {
					if ( ! is_dir( $value ) && strstr( $value, '.log' ) ) {
						$result[ sanitize_title( $value ) ] = $value;
					}
				}
			}
		}

		return $result;
	}
}
