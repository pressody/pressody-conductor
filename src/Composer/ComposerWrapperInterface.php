<?php
/**
 * Composer wrapper interface.
 *
 * @since   0.8.0
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

namespace Pressody\Conductor\Composer;

/**
 * Client interface.
 *
 * @since   0.8.0
 * @package Pressody
 */
interface ComposerWrapperInterface {

	/**
	 * Runs composer install.
	 *
	 * @param string $composer_json_path The absolute path to the composer.json to use.
	 * @param array  $args               Various args to change Composer's behavior. These overwrite any configuration Composer determines by itself.
	 *                                   Provide entry `revert_file_path` as an absolute path to a composer.json backup to revert to in case of errors.
	 *
	 * @return bool
	 */
	public function install( string $composer_json_path, array $args = [] ): bool;
}
