<?php
/**
 * Git repository interaction.
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
 * Git repository interaction class.
 *
 * @since   0.10.0
 * @package Pressody
 */
class GitRepo implements GitRepoInterface {

	/**
	 * The absolute path to the Git repo directory.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	protected string $repo_dir;

	/**
	 * @since 0.10.0
	 *
	 * @var GitWrapper|null
	 */
	protected ?GitWrapper $git_wrapper = null;

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
	 * @param string          $repo_dir The absolute path to the Git repo directory.
	 * @param GitWrapper      $git_wrapper
	 * @param LoggerInterface $logger   Logger.
	 */
	public function __construct(
		string $repo_dir,
		GitWrapper $git_wrapper,
		LoggerInterface $logger
	) {
		$this->repo_dir    = $repo_dir;
		$this->git_wrapper = $git_wrapper;
		$this->logger      = $logger;
	}

	/**
	 * Determine if we can interact with the Git repo (run commands).
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function can_interact(): bool {
		return $this->git_wrapper->can_exec_git() && $this->git_wrapper->is_status_working();
	}

	/**
	 * Get the absolute path of the Git repo.
	 *
	 * @since 0.10.0
	 *
	 * @return string The absolute path to the Git repo root or empty string on failure.
	 */
	public function get_repo_path(): string {
		return $this->repo_dir ?? '';
	}

	/**
	 * Get the installed git version.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->git_wrapper->get_version();
	}

	/**
	 * Checks if we have an actual Git repo.
	 *
	 * @since 0.10.0
	 *
	 * @param string $path Optional. Absolute path to the directory to test.
	 *                     Default is empty, meaning that it tests the directory with which the Git wrapper was initialized with.
	 *
	 * @return bool
	 */
	public function is_repo( string $path = '' ): bool {
		if ( empty( $path ) ) {
			$path = $this->repo_dir;
		}
		$realpath   = realpath( $path . '/.git' );
		$git_config = realpath( $realpath . '/config' );
		$git_index  = realpath( $realpath . '/index' );
		if ( ! empty( $realpath ) && is_dir( $realpath ) && file_exists( $git_config ) && file_exists( $git_index ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get uncommitted changes.
	 *
	 * It returns an array like this:
	 * [
	 *  file => deleted|modified
	 *  ...
	 * ]
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_local_changes(): array {
		list( $return, $response ) = $this->git_wrapper->_call( 'status', '--porcelain' );

		if ( 0 !== $return ) {
			return [];
		}
		$new_response = [];
		if ( ! empty( $response ) ) {
			foreach ( $response as $line ) {
				// For details on statuses see https://git-scm.com/docs/git-status/2.2.3#_output
				$work_tree_status = substr( $line, 1, 1 );
				$path             = substr( $line, 3 );

				if ( ( '"' == $path[0] ) && ( '"' == $path[ strlen( $path ) - 1 ] ) ) {
					// git status --porcelain will put quotes around paths with whitespaces
					// we don't want the quotes, let's get rid of them
					$path = substr( $path, 1, strlen( $path ) - 2 );
				}

				switch ( $work_tree_status ) {
					case 'D':
						$action = 'delete';
						break;
					case '?':
						$action = 'add';
						break;
					default:
						$action = 'modify';
						break;
				}

				$new_response[ $path ] = $action;
			}
		}

		return $new_response;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return mixed
	 */
	public function get_uncommitted_changes() {
		list( , $changes ) = $this->git_wrapper->status();

		return $changes;
	}

	/**
	 *
	 * @see https://git-scm.com/docs/git-status/2.2.3#_output
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function local_status(): array {
		return $this->git_wrapper->local_status();
	}

	/**
	 *
	 * @see https://git-scm.com/docs/git-status/2.2.3#_output
	 *
	 * @since 0.10.0
	 *
	 * @param bool $local_only
	 *
	 * @return array
	 */
	public function status( bool $local_only = false ): array {
		return $this->git_wrapper->status();
	}

	/**
	 * Checks if repo has uncommitted changes.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_dirty(): bool {
		$changes = $this->get_uncommitted_changes();

		return ! empty( $changes );
	}

	/**
	 * Add a remote URL for origin.
	 *
	 * @since 0.10.0
	 *
	 * @param string $url The remote URL to add.
	 *
	 * @return bool
	 */
	public function add_remote_url( string $url ): bool {
		return $this->git_wrapper->add_remote_url( $url );
	}

	/**
	 * Get the repo's remote origin URL.
	 *
	 * @since 0.10.0
	 *
	 * @return string The remote URL. Empty string on missing remote URL.
	 */
	public function get_remote_url(): string {
		return $this->git_wrapper->get_remote_url();
	}

	/**
	 * Remove the repo's remote origin setting.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function remove_remote(): bool {
		return $this->git_wrapper->remove_remote();
	}

	/**
	 * @param string $commit
	 *
	 * @since 0.10.0
	 *
	 * @return string|false
	 */
	public function get_commit_message( string $commit ) {
		return $this->git_wrapper->get_commit_message( $commit );
	}

	/**
	 * Return the last n commits
	 *
	 * @since 0.10.0
	 *
	 * @param int $number
	 *
	 * @return array|false
	 */
	function get_last_commits( int $number = 20 ) {
		list( $return, $message ) = $this->git_wrapper->_call( 'log', '-n', $number, '--pretty=format:%s' );
		if ( 0 !== $return ) {
			return false;
		}

		list( $return, $response ) = $this->git_wrapper->_call( 'log', '-n', $number, '--pretty=format:%h|%an|%ae|%ad|%cn|%ce|%cd' );
		if ( 0 !== $return ) {
			return false;
		}

		$commits = [];
		foreach ( $response as $index => $value ) {
			$commit_info                = explode( '|', $value );
			$commits[ $commit_info[0] ] = [
				'subject'      => $message[ $index ],
				'author_name'  => $commit_info[1],
				'author_email' => $commit_info[2],
				'author_date'  => $commit_info[3],
			];
			if ( $commit_info[1] != $commit_info[4] && $commit_info[2] != $commit_info[5] ) {
				$commits[ $commit_info[0] ]['committer_name']  = $commit_info[4];
				$commits[ $commit_info[0] ]['committer_email'] = $commit_info[5];
				$commits[ $commit_info[0] ]['committer_date']  = $commit_info[6];
			}
		}

		return $commits;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $content
	 *
	 * @return bool
	 */
	public function set_gitignore( string $content ): bool {
		return false !== file_put_contents( $this->repo_dir . '/.gitignore', $content );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return string|false
	 */
	public function get_gitignore() {
		return file_get_contents( $this->repo_dir . '/.gitignore' );
	}

	/**
	 * Remove files version control index.
	 *
	 * @since 0.10.0
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function rm_cached( string $path ): bool {
		return $this->git_wrapper->rm_cached( $path );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function fetch_ref(): bool {
		return $this->git_wrapper->fetch_ref();
	}

	/**
	 * @since 0.10.0
	 *
	 * @param ...$commits
	 *
	 * @return bool
	 */
	public function merge_with_accept_mine( ...$commits ): bool {
		do_action( 'pressody_conductor/git/before_merge_with_accept_mine', $commits );

		if ( 1 == count( $commits ) && is_array( $commits[0] ) ) {
			$commits = $commits[0];
		}

		$ahead_commits = $this->git_wrapper->get_ahead_commits();

		// Combine all commits with the ahead commits.
		$commits = array_unique( array_merge( array_reverse( $commits ), $ahead_commits ) );
		$commits = array_reverse( $commits );

		$remote_branch = $this->git_wrapper->get_remote_tracking_branch();
		$local_branch = $this->git_wrapper->get_local_branch();
		// Rename the local branch to 'merge_local'
		$this->git_wrapper->_call( 'branch', '-m', 'merge_local' );

		// Local branch set up to track remote branch.
		$this->git_wrapper->_call( 'branch', $local_branch, $remote_branch );

		// Checkout to the $local_branch.
		list( $return, ) = $this->git_wrapper->_call( 'checkout', $local_branch );
		if ( $return != 0 ) {
			$this->git_wrapper->_call( 'branch', '-M', $local_branch );

			$this->logger->error( 'Failed to checkout to local branch.',
				[
					'logCategory' => 'git',
				]
			);

			return false;
		}

		// Don't cherry-pick if there are no commits
		if ( count( $commits ) > 0 ) {
			$this->cherry_pick_commits( $commits );
		}

		// Git status without states: AA, DD, UA, AU ...
		if ( $this->successfully_merged() ) {
			// Delete the 'merge_local' branch.
			$this->git_wrapper->_call( 'branch', '-D', 'merge_local' );

			return true;
		} else {
			$this->git_wrapper->_call( 'cherry-pick', '--abort' );
			$this->git_wrapper->_call( 'checkout', '-b', 'merge_local' );
			$this->git_wrapper->_call( 'branch', '-M', $local_branch );

			$this->logger->error( 'Failed to merge with accept mine.',
				[
					'logCategory' => 'git',
				]
			);

			return false;
		}
	}

	/**
	 * @since 0.10.0
	 *
	 * @param array $commits
	 *
	 * @return bool
	 */
	protected function cherry_pick_commits( array $commits ): bool {
		foreach ( $commits as $commit ) {
			if ( empty( $commit ) ) {
				return false;
			}

			list( $return, $response ) = $this->git_wrapper->_call( 'cherry-pick', $commit );

			// Abort the cherry-pick if the changes are already pushed.
			if ( false !== $this->strpos_haystack_array( $response, 'previous cherry-pick is now empty' ) ) {
				$this->git_wrapper->_call( 'cherry-pick', '--abort' );
				continue;
			}

			if ( $return != 0 ) {
				$this->resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			}
		}

		return true;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param     $haystack
	 * @param     $needle
	 * @param int $offset
	 *
	 * @return bool
	 */
	protected function strpos_haystack_array( $haystack, $needle, int $offset = 0 ): bool {
		if ( ! is_array( $haystack ) ) {
			$haystack = [ $haystack ];
		}

		foreach ( $haystack as $query ) {
			if ( strpos( $query, $needle, $offset ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param $message
	 *
	 * @return false|string The commit result or false on failure.
	 */
	protected function resolve_merge_conflicts( $message ) {
		list( , $changes ) = $this->status( true );
		// @todo Maybe log.
		foreach ( $changes as $path => $change ) {
			if ( in_array( $change, [ 'UD', 'DD' ] ) ) {
				$this->git_wrapper->_call( 'rm', $path );
				$message .= "\n\tConflict: $path [removed]";
			} else if ( 'DU' == $change ) {
				$this->git_wrapper->_call( 'add', $path );
				$message .= "\n\tConflict: $path [added]";
			} else if ( in_array( $change, [ 'AA', 'UU', 'AU', 'UA' ] ) ) {
				$this->git_wrapper->_call( 'checkout', '--theirs', $path );
				$this->git_wrapper->_call( 'add', '--all', $path );
				$message .= "\n\tConflict: $path [local version]";
			}
		}

		return $this->commit( $message );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	protected function successfully_merged(): bool {
		list( , $response ) = $this->status( true );
		$changes = array_values( $response );

		return ( 0 == count( array_intersect( $changes, [ 'DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU' ] ) ) );
	}

	/**
	 * @since 0.10.0
	 *
	 * @param array|string $args
	 *
	 * @return int
	 */
	public function add( $args ): int {
		if ( ! is_array( $args ) ) {
			$args = [ $args ];
		}

		return $this->git_wrapper->add( $args );
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $message
	 * @param string $author_email
	 * @param string $author_name
	 *
	 * @return string|false The response on success or false on failure.
	 */
	public function commit( string $message, string $author_email = '', string $author_name = '' ) {
		$author = '';
		if ( $author_email ) {
			if ( empty( $author_name ) ) {
				$author_name = $author_email;
			}
			$author = "$author_name <$author_email>";
		}

		return $this->git_wrapper->commit( $message, $author );
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $branch
	 *
	 * @return bool
	 */
	public function push( string $branch = '' ): bool {
		return $this->git_wrapper->push( $branch );
	}
}
