<?php
/**
 * Route interface.
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

namespace Pressody\Conductor\Route;

use Pressody\Conductor\HTTP\Request;
use Pressody\Conductor\HTTP\Response;

/**
 * Route interface.
 *
 * @package Pressody
 * @since 0.1.0
 */
interface Route {
	/**
	 * Handle a request.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request HTTP request.
	 * @return Response
	 */
	public function handle( Request $request ): Response;
}
