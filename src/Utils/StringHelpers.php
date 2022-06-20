<?php
/**
 * This is a utility class that groups all our string related helper functions.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\Utils;

/**
 * String Helper class.
 *
 * @since   0.1.0
 * @package Pressody
 */
class StringHelpers {

	/**
	 * Determine if a string matches an asterisk pattern.
	 *
	 * If no asterisk then a full match will be made.
	 *
	 * Code taken from: https://stackoverflow.com/a/50963365 and adapted.
	 *
	 * @param string $pattern
	 * @param string $str
	 *
	 * @return bool
	 */
	public static function partial_match_string( string $pattern, string $str ): bool {
		$pattern = preg_quote( $pattern, '/');
		$pattern = preg_replace_callback( '/([^\*])/', 'testPRC', $pattern );
		$pattern = str_replace( '\*', '.*', $pattern );

		return (bool) preg_match( '/^' . $pattern . '$/i', $str );
	}

	protected static function testPRC( $m ) {
		return preg_quote( $m[1], "/" );
	}

}
