<?php
/**
 * Git repository interface.
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
 * Git repository interface.
 *
 * @since   0.10.0
 * @package Pressody
 */
interface GitRepoInterface {

	/**
	 * Determine if we can interact with the Git repo (run commands).
	 *
	 * @return bool
	 */
	public function can_interact(): bool;

	/**
	 * Get the absolute path of the Git repo.
	 *
	 * @return string The absolute path to the Git repo root or empty string on failure.
	 */
	public function get_repo_path(): string;

	/**
	 * Get the installed git version.
	 *
	 * @return string
	 */
	public function get_version(): string;

	/**
	 * Checks if we have an actual Git repo.
	 *
	 * @param string $path Optional. Absolute path to the directory to test.
	 *                     Default is empty, meaning that it tests the directory with which the Git wrapper was initialized with.
	 *
	 * @return bool
	 */
	public function is_repo( string $path = '' ): bool;

	/**
	 * Get uncommitted changes with status porcelain.
	 * git status --porcelain
	 * It returns an array like this:
	 * [
	 *  file => deleted|modified
	 *  ...
	 * ]
	 *
	 * @return array
	 */
	public function get_local_changes(): array;

	/**
	 * @return mixed
	 */
	public function get_uncommitted_changes();

	/**
	 * @return array
	 */
	public function local_status(): array;

	/**
	 * @param bool $local_only
	 *
	 * @return array
	 */
	public function status( bool $local_only = false ): array;

	/**
	 * Checks if repo has uncommitted changes.
	 * git status --porcelain
	 *
	 * @return bool
	 */
	public function is_dirty(): bool;

	/**
	 * Add a remote URL for origin.
	 *
	 * @param string $url The remote URL to add.
	 *
	 * @return bool
	 */
	public function add_remote_url( string $url ): bool;

	/**
	 * Get the repo's remote origin URL.
	 *
	 * @return string The remote URL. Empty string on missing remote URL.
	 */
	public function get_remote_url(): string;

	/**
	 * Remove the repo's remote origin setting.
	 *
	 * @return bool
	 */
	public function remove_remote(): bool;

	/**
	 * @param string $commit
	 *
	 * @return string|false
	 */
	public function get_commit_message( string $commit );

	/**
	 * Return the last n commits
	 *
	 * @param int $number
	 *
	 * @return array|false
	 */
	function get_last_commits( int $number = 20 );

	/**
	 * @param string $content
	 *
	 * @return bool
	 */
	public function set_gitignore( string $content ): bool;

	/**
	 * @return string|false
	 */
	public function get_gitignore();

	/**
	 * Remove files version control index.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function rm_cached( string $path ): bool;

	/**
	 * @return bool
	 */
	public function fetch_ref(): bool;

	/**
	 * @param ...$commits
	 *
	 * @return bool
	 */
	public function merge_with_accept_mine( ...$commits ): bool;

	/**
	 * @param array|string $args
	 *
	 * @return int
	 */
	public function add( $args ): int;

	/**
	 * @param string $message
	 * @param string $author_email
	 * @param string $author_name
	 *
	 * @return string|false The response on success or false on failure.
	 */
	public function commit( string $message, string $author_email = '', string $author_name = '' );

	/**
	 * @param string $branch
	 *
	 * @return bool
	 */
	public function push( string $branch = '' ): bool;
}
