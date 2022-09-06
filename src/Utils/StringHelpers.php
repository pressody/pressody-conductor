<?php
/**
 * This is a utility class that groups all our string related helper functions.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
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
