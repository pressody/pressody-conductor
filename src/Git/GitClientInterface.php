<?php
/**
 * Git client interface.
 *
 * @since   0.10.0
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

namespace Pressody\Conductor\Git;

/**
 * Git client interface.
 *
 * @since   0.10.0
 * @package Pressody
 */
interface GitClientInterface {

	/**
	 * Determine if we have a Git repo and that we can interact with it (run commands).
	 *
	 * @return bool
	 */
	public function can_interact(): bool;

	/**
	 * Get the absolute path of the Git repo that we can interact with.
	 *
	 * @return string The absolute path to the Git repo root or empty string on failure.
	 */
	public function get_git_repo_path(): string;

	/**
	 * Checks if repo has uncommitted changes.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_repo_dirty(): bool;

	/**
	 * Get uncommitted changes.
	 *
	 * It returns an array like this:
	 * [
	 *      file_path => deleted|modified
	 *      ...
	 * ]
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_local_changes(): array;

	/**
	 * Commit the changes with a certain message.
	 *
	 * @param string $message
	 * @param string $path Optional. Specify a path to add before commit.
	 *                     Default is the current directory.
	 *
	 * @return false|mixed
	 */
	public function commit_changes( string $message, string $path = '.' );

	/**
	 * Merges the commits with remote and pushes them back
	 *
	 * @since 0.10.0
	 *
	 * @param array $commits
	 *
	 * @return bool
	 */
	public function merge_and_push( array $commits ): bool;

	/**
	 * Formats a commit message.
	 *
	 * @since   0.10.0
	 *
	 * @param string $message The message to format.
	 * @param array  $context Additional information.
	 *
	 * @return string
	 */
	public function format_message( string $message, array $context = [] ): string;

	/**
	 * Get the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 * @return string|false The contents or false on failure.
	 */
	public function read_gitignore();

	/**
	 * Write the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 * @return bool True on success or false on failure.
	 */
	public function write_gitignore( string $contents ): bool;
}
