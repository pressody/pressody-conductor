<?php
/**
 * JSON response body.
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

/**
 * JSON response body class.
 *
 * @since 0.1.0
 */
class JsonBody implements ResponseBody {
	/**
	 * Message data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Create a JSON response body.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $data Response data.
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Emit the data as a JSON-serialized string.
	 *
	 * @since 0.1.0
	 */
	public function emit() {
		echo wp_json_encode( $this->data );
	}
}
