<?php
/**
 * HTTP exception.
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
use WP_Http as HTTP;

/**
 * HTTP exception class.
 *
 * @since 0.1.0
 */
class HttpException extends \Exception implements ExceptionInterface {
	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected $status_code;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string         $message     Message.
	 * @param int            $status_code Optional. HTTP status code. Defaults to 500.
	 * @param int            $code        Exception code.
	 * @param Throwable|null $previous    Previous exception.
	 */
	public function __construct(
		string $message,
		int $status_code = HTTP::INTERNAL_SERVER_ERROR,
		int $code = 0,
		Throwable $previous = null
	) {
		$this->status_code = $status_code;
		$message           = $message ?: 'Internal Server Error';

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Create an exception for a forbidden resource request.
	 *
	 * @since 0.1.0
	 *
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return HTTPException
	 */
	public static function forForbiddenResource(
		int $code = 0,
		Throwable $previous = null
	): HttpException {
		$user_id     = get_current_user_id();
		$request_uri = $_SERVER['REQUEST_URI'];
		$message     = "Forbidden resource requested; User: {$user_id}; URI: {$request_uri}";

		return new static( $message, HTTP::FORBIDDEN, $code, $previous );
	}

	/**
	 * Retrieve the HTTP status code.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}
}
