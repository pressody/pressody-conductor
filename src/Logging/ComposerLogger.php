<?php
/**
 * Composer Logger/IO that dispatches log messages to the registered handlers.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.8.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\Logging;

use Pressody\Conductor\Utils\ArrayHelpers;
use Psr\Log\LogLevel;

/**
 * Composer logger/IO class.
 *
 * @since 0.8.0
 */
class ComposerLogger extends Logger {

	protected int $verbosity = self::NORMAL;

	public function setVerbosity( $verbosity ) {
		$this->verbosity = $verbosity;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isVerbose() {
		return $this->verbosity >= self::VERBOSE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isVeryVerbose() {
		return $this->verbosity >= self::VERY_VERBOSE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isDebug() {
		return $this->verbosity >= self::DEBUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($messages, $newline = true, $verbosity = self::NORMAL) {
		// Do not handle any message that has a verbosity larger than our intended level.
		if ( $verbosity > $this->verbosity ) {
			return;
		}

		// Cleanup the messages that Composer generates.
		// Extract the links into regular URLs.
		$messages = (array) preg_replace( '#<(https?)([^>]+)>#', '$1$2', $messages );
		// Extract the level XML tags into levels (the array keys) and actual messages.
		$messages = ArrayHelpers::array_map_assoc( function ( $key, $message ) {
			// First, we want to identify the message type that Composer encodes as an XML tag.
			$level = LogLevel::INFO; // default level
			if ( preg_match( '/^<([^>]+)>/i', $message, $matches ) && ! empty( $matches[1] ) ) {
				if ( LogLevels::is_valid_level( strtolower( $matches[1] ) ) ) {
					$level = strtolower( $matches[1] );
				}
			}
			return [ $level => strip_tags( trim( $message ) ) ];
		}, $messages );

		// Go through messages and log at once all subsequent messages with the same level.
		$separator = ' | ';
		if ( $newline ) {
			$separator = PHP_EOL;
		}

		$levels = array_keys( $messages );
		$messages = array_values( $messages );

		$current_level = reset( $levels );
		$current_message = [ reset( $messages ) ];
		$idx = 1;
		while ( $idx < count( $messages ) ) {
			if ( $levels[ $idx ] === $current_level ) {
				$current_message[] = $messages[ $idx ];
			} else {
				// We have encountered a level change.
				// Log and start a new message.
				$this->log( $current_level, implode( $separator, $current_message ) );
				$current_level = $levels[ $idx ];
				$current_message = [ $messages[ $idx ] ];
			}

			$idx++;
		}

		// Log the leftover.
		$this->log( $current_level, implode( $separator, $current_message ), ['logCategory' => 'composer',] );
	}
}
