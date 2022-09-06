<?php
/**
 * Invalid file name exception.
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
 * Invalid file name exception class.
 *
 * @since 0.1.0
 */
class InvalidFileName extends \InvalidArgumentException implements ExceptionInterface {
	/**
	 * Create an exception for an invalid file name argument.
	 *
	 * @since 0.1.0
	 *
	 * @param string          $filename        File name.
	 * @param int             $validation_code Validation code returned from validate_file().
	 * @param int             $code            Optional. The Exception code.
	 * @param Throwable|null $previous        Optional. The previous throwable used for the exception chaining.
	 *
	 * @return InvalidFileName
	 */
	public static function withValidationCode(
		string $filename,
		int $validation_code,
		int $code = 0,
		Throwable $previous = null
	): InvalidFileName {
		$message = "File name '{$filename}' ";

		switch ( $validation_code ) {
			case 1:
				$message .= ' contains directory traversal.';
				break;
			case 2:
				$message .= ' contains a Windows drive path.';
				break;
			case 3:
				$message .= ' is not in the allowed files list.';
				break;
		}

		return new static( $message, $code, $previous );
	}
}
