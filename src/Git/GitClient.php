<?php
/**
 * Git client.
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

use Psr\Log\LoggerInterface;

/**
 * Git client.
 *
 * @since   0.10.0
 * @package Pressody
 */
class GitClient implements GitClientInterface {

	/**
	 * The Git repository to interact with.
	 *
	 * @since 0.10.0
	 *
	 * @var GitRepoInterface
	 */
	protected GitRepoInterface $git_repo;

	/**
	 * Logger.
	 *
	 * @since 0.10.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param GitRepoInterface $git_repo The Git repository to use.
	 * @param LoggerInterface  $logger   Logger.
	 */
	public function __construct(
		GitRepoInterface $git_repo,
		LoggerInterface $logger
	) {
		$this->git_repo = $git_repo;
		$this->logger   = $logger;
	}

	/**
	 * Determine if we have a Git repo and that we can interact with it (run commands).
	 *
	 * @return bool
	 */
	public function can_interact(): bool {
		return $this->git_repo->is_repo() && $this->git_repo->can_interact();
	}

	/**
	 * Get the absolute path of the Git repo that we can interact with.
	 *
	 * @return string The absolute path to the Git repo root or empty string on failure.
	 */
	public function get_git_repo_path(): string {
		return $this->git_repo->get_repo_path();
	}

	/**
	 * Formats a commit message.
	 *
	 * - Interpolates context values into message placeholders.
	 * - Appends additional context data as JSON, in the commit message body.
	 *
	 * @since   0.10.0
	 *
	 * @param string $message        The main message to format.
	 * @param array  $context        Optional. Context information to structure/enhance the message by.
	 *                               Some special entries that we support are:
	 *                               - `commitCategory`  A category to group commits by.
	 *                               - `body`          A commit message body to include.
	 *
	 * @return string
	 */
	public function format_message( string $message, array $context = [] ): string {
		$category = '';
		// If we have been provided with a commit category, include it.
		if ( isset( $context['commitCategory'] ) && ! empty( trim( $context['commitCategory'] ) ) ) {
			$category = '[' . strtoupper( trim( $context['commitCategory'] ) ) . '] ';
		}

		$entry = "{$category}{$message}";

		// If we have been provided with a commit body, include it with an empty line.
		if ( isset( $context['body'] ) && ! empty( trim( $context['body'] ) ) ) {
			$entry .= PHP_EOL . PHP_EOL . $context['body'];
		}

		$search  = [];
		$replace = [];

		$temp_context = $context;
		foreach ( $temp_context as $key => $value ) {
			$placeholder = '{' . $key . '}';

			if ( false === strpos( $message, $placeholder ) ) {
				continue;
			}

			array_push( $search, '{' . $key . '}' );
			array_push( $replace, $this->to_string( $value ) );
			unset( $temp_context[ $key ] );
		}

		$entry = str_replace( $search, $replace, $entry );

		// Append additional context data in the commit body.
		if ( isset( $temp_context['commitCategory'] ) ) {
			unset( $temp_context['commitCategory'] );
		}
		if ( ! empty( $temp_context ) ) {
			$entry .= PHP_EOL . PHP_EOL . 'ADDITIONAL CONTEXT: ' . json_encode( $temp_context, \JSON_PRETTY_PRINT );
		}

		return apply_filters(
			'pressody_conductor/git/format_message',
			$entry,
			[
				'message' => $message,
				'context' => $context,
			]
		);
	}

	/**
	 * Get the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 *
	 * @return string|false The contents or false on failure.
	 */
	public function read_gitignore() {
		return $this->git_repo->get_gitignore();
	}

	/**
	 * Write the contents of the site's .gitignore file.
	 *
	 * @since 0.10.0
	 *
	 * @return bool True on success or false on failure.
	 */
	public function write_gitignore( string $contents ): bool {
		return $this->git_repo->set_gitignore( $contents );
	}

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
	public function get_local_changes(): array {
		return $this->git_repo->get_local_changes();
	}

	/**
	 * Checks if repo has uncommitted changes.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_repo_dirty(): bool {
		return $this->git_repo->is_dirty();
	}

	/**
	 * Commit the changes with a certain message.
	 *
	 * @since 0.10.0
	 *
	 * @param string $message
	 * @param string $path Optional. Specify a path to add before commit.
	 *                     Default is the current directory.
	 *
	 * @return false|mixed
	 */
	public function commit_changes( string $message, string $path = '.' ) {
		if ( ! empty( $path ) ) {
			$this->git_repo->rm_cached( $path );
			$this->git_repo->add( $path );
		}

		$current_user = wp_get_current_user();

		return $this->git_repo->commit( $message, $current_user->user_email, $current_user->display_name );
	}

	/**
	 * Merges the commits with remote and pushes them back
	 *
	 * @since 0.10.0
	 *
	 * @param array|string|false $commits A commit hash, a list of commit hashes or false for no commits.
	 *
	 * @return bool
	 */
	public function merge_and_push( $commits ): bool {
		$lock = $this->acquire_merge_lock();
		if ( ! $lock ) {
			$this->logger->error( 'Failed to acquire the Git merge lock due to timeout.',
				[
					'logCategory' => 'git',
				]
			);

			return false;
		}

		if ( ! $this->git_repo->fetch_ref() ) {
			return false;
		}

		$merge_status = $this->git_repo->merge_with_accept_mine( $commits );

		$this->release_merge_lock( $lock );

		return $this->git_repo->push() && $merge_status;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return array|false
	 */
	protected function acquire_merge_lock() {
		$lock_path   = apply_filters( 'pressody_conductor/lock_path', \trailingslashit( sys_get_temp_dir() ) . '.pressody-conductor-git-lock' );
		$lock_handle = fopen( $lock_path, 'w+' );

		$lock_timeout    = intval( ini_get( 'max_execution_time' ) ) > 10 ? intval( ini_get( 'max_execution_time' ) ) - 5 : 10;
		$lock_timeout_ms = 10;
		$lock_retries    = 0;
		while ( ! flock( $lock_handle, LOCK_EX | LOCK_NB ) ) {
			usleep( $lock_timeout_ms * 1000 );
			$lock_retries ++;
			if ( $lock_retries * $lock_timeout_ms > $lock_timeout * 1000 ) {
				return false; // timeout
			}
		}

		return [ $lock_path, $lock_handle ];
	}

	/**
	 * @since 0.10.0
	 *
	 * @param $lock
	 */
	protected function release_merge_lock( $lock ) {
		list( $lock_path, $lock_handle ) = $lock;
		flock( $lock_handle, LOCK_UN );
		fclose( $lock_handle );
	}

	/**
	 * Convert a value to a string.
	 *
	 * @since 0.10.0
	 *
	 * @param mixed $value Message.
	 *
	 * @return string
	 */
	protected function to_string( $value ): string {
		if ( is_wp_error( $value ) ) {
			$value = $value->get_error_message();
		} elseif ( is_object( $value ) && method_exists( '__toString', $value ) ) {
			$value = (string) $value;
		} elseif ( ! is_scalar( $value ) ) {
			$value = wp_json_encode( $value, \JSON_UNESCAPED_SLASHES, 128 );
		}

		return (string) $value;
	}
}
