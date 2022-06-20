<?php
/**
 * Route interface.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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
