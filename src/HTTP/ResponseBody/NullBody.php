<?php
/**
 * Null response body.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Conductor\HTTP\ResponseBody;

/**
 * Null response body class.
 *
 * @since 0.1.0
 */
class NullBody implements ResponseBody {
	/**
	 * Emit the body.
	 *
	 * @since 0.1.0
	 */
	public function emit() {
		// Silence.
	}
}
