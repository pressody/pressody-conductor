<?php
/**
 * This is a utility class that groups all our time related helper functions.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\Utils;

/**
 * Time Helper class.
 *
 * @since   0.1.0
 * @package Pressody
 */
class TimeHelpers {

	/**
	 * Return a human-readable time elapsed string for a given datetime.
	 *
	 * Code taken from: https://stackoverflow.com/a/18602474.
	 *
	 * @param string $datetime Any datetime format supported (https://www.php.net/manual/en/datetime.formats.php).
	 * @param bool $full Optional. The format of the returned string.
	 *                   false to return a more readable, rounded format (e.g. '4 hours ago').
	 *                   true to return a more verbose, exact format (e.g. '4 hours, 26 minutes, 4 seconds ago').
	 *
	 * @return string|null The converted datetime string. null on parsing errors.
	 */
	public static function time_elapsed_string( string $datetime, bool $full = false ): ?string {
		try {
			$now = new \DateTime;
			$ago = new \DateTime( $datetime );
		} catch ( \Exception $e ) {
			return null;
		}
		$diff = $now->diff( $ago );

		$diff->w = floor( $diff->d / 7 );
		$diff->d -= $diff->w * 7;

		$string = array(
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		);
		foreach ( $string as $k => &$v ) {
			if ( $diff->$k ) {
				$v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
			} else {
				unset( $string[ $k ] );
			}
		}

		if ( ! $full ) {
			$string = array_slice( $string, 0, 1 );
		}

		return $string ? implode( ', ', $string ) . ' ago' : 'just now';
	}

}
