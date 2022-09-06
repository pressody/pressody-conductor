<?php
/**
 * File not found exception.
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

namespace Pressody\Conductor\Exception;

use Throwable;

/**
 * File not found exception class.
 *
 * @since 0.1.0
 */
class FileNotFound extends \RuntimeException implements ExceptionInterface {
	/**
	 * Create an exception for invalid checksum operations.
	 *
	 * @param string          $filename The filename that couldn't be found.
	 * @param int             $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return FileNotFound
	 */
	public static function forInvalidChecksum(
		string $filename,
		int $code = 0,
		Throwable $previous = null
	): FileNotFound {
		$message = "Cannot compute a checksum for an unknown file at {$filename}.";

		return new static( $message, $code, $previous );
	}
}
