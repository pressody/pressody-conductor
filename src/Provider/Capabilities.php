<?php
/**
 * Capabilities provider.
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

namespace Pressody\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Conductor\Capabilities as Caps;
use function Pressody\Conductor\user_has_role;

/**
 * Capabilities provider class.
 *
 * @since 0.1.0
 */
class Capabilities extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_filter( 'map_meta_cap', [ $this, 'map_meta_cap' ], 10, 4 );
		add_filter( 'user_has_cap', [$this, 'handle_caps' ], 999, 4 );
	}

	/**
	 * Map meta capabilities to primitive capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $caps Returns the user's actual capabilities.
	 * @param string $cap  Capability name.
	 * @return array
	 */
	public function map_meta_cap( array $caps, string $cap ): array {
		switch ( $cap ) {

		}

		return $caps;
	}

	/**
	 * Change user capabilities as necessary.
	 *
	 * @since 0.1.0
	 *
	 * @param bool[]   $allcaps An array of all the user's capabilities.
	 * @param string[] $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args {
	 *     Arguments that accompany the requested capability check.
	 *
	 *     @type string    $0 Requested capability.
	 *     @type int       $1 Concerned user ID.
	 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
	 * }
	 * @param \WP_User  $user    The user object.
	 * @return bool[] Modified array of the user's capabilities.
	 */
	public function handle_caps( $allcaps, $caps, $args, $user ) {
		if ( ! user_has_role( $user, 'pressody_conductor_support' ) ) {
			$allcaps['view_site_health_checks'] = false;
		}

		return $allcaps;
	}
}
