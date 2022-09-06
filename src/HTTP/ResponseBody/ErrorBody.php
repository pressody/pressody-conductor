<?php
/**
 * HTTP error message body.
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

namespace Pressody\Conductor\HTTP\ResponseBody;

use WP_Http as HTTP;

/**
 * HTTP error message body class.
 *
 * @since 0.1.0
 */
class ErrorBody implements ResponseBody {
	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected $status_code;

	/**
	 * Create an error message body.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message     Error message.
	 * @param int    $status_code Optional. HTTP status code.
	 */
	public function __construct( string $message, int $status_code = HTTP::INTERNAL_SERVER_ERROR ) {
		$this->message     = $message;
		$this->status_code = $status_code;
	}

	/**
	 * Display the error message.
	 *
	 * @since 0.1.0
	 */
	public function emit() {
		wp_die(
			wp_kses_data( $this->message ),
			absint( $this->status_code )
		);
	}
}
