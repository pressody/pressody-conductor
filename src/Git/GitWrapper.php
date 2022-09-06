<?php
/**
 * Git shell wrapper.
 *
 * Borrowed (and modified) from Gitium: https://github.com/presslabs/gitium/blob/master/gitium/inc/class-git-wrapper.php
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
use function Pressody\Conductor\plugin;

/**
 * Git shell wrapper class.
 *
 * @since   0.10.0
 * @package Pressody
 */
class GitWrapper {

	/**
	 * The absolute path to the Git repository directory.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	private string $repo_dir;

	/**
	 * Logger.
	 *
	 * @since 0.10.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * The last error encountered.
	 *
	 * @since 0.10.0
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

	function __construct(
		string $repo_dir,
		LoggerInterface $logger
	) {
		$this->repo_dir = $repo_dir;
		$this->logger = $logger;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return array
	 */
	private function get_env(): array {
		$env = [];
		if ( defined( 'PD_GIT_SSH' ) && PD_GIT_SSH ) {
			$env['GIT_SSH'] = PD_GIT_SSH;
		} else {
			$env['GIT_SSH'] = plugin()->get_path( 'bin/ssh-git' );
		}

		if ( defined( 'PD_GIT_KEY_FILE' ) && PD_GIT_KEY_FILE ) {
			$env['GIT_KEY_FILE'] = PD_GIT_KEY_FILE;
		}

		return $env;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param ...$args
	 *
	 * @return array
	 */
	public function _call( ...$args ): array {
		$args     = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd      = "git $args 2>&1";
		$return   = - 1;
		$response = [];
		$env      = $this->get_env();

		$proc = proc_open(
			$cmd,
			[
				0 => [ 'pipe', 'r' ],  // stdin
				1 => [ 'pipe', 'w' ],  // stdout
			],
			$pipes,
			$this->repo_dir,
			$env
		);
		if ( is_resource( $proc ) ) {
			fclose( $pipes[0] );
			while ( $line = fgets( $pipes[1] ) ) {
				$response[] = rtrim( $line, "\n\r" );
			}
			$return = (int) proc_close( $proc );
		} else {
			$this->logger->error( 'Failed git command `{cmd}` due to proc_open() failure.',
				[
					'cmd' => $cmd,
					'logCategory' => 'git',
				]
			);
		}

		if ( 0 !== $return ) {
			$this->last_error = join( "\n", $response );

			$this->logger->error( 'Failed git command `{cmd}`: {response}',
				[
					'cmd' => $cmd,
					'response' => $this->last_error,
					'logCategory' => 'git',
				]
			);
		} else {
			$this->last_error = null;
		}

		return [ $return, $response ];
	}

	/**
	 * @since 0.10.0
	 *
	 * @return null|string
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function can_exec_git(): bool {
		list( $return, ) = $this->_call( 'version' );

		return ( 0 === $return );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_status_working(): bool {
		list( $return, ) = $this->_call( 'status', '-s' );

		return ( 0 == $return );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_version(): string {
		list( $return, $version ) = $this->_call( 'version' );
		if ( 0 !== $return ) {
			return '';
		}

		// The return is something like `git version 2.25.1`.
		if ( ! empty( $version[0] ) ) {
			return str_replace( 'git version ', '', $version[0] );
		}

		return '';
	}

	/**
	 * @since 0.10.0
	 *
	 * @return mixed
	 */
	public function get_ahead_commits() {
		list( , $commits ) = $this->_call( 'rev-list', '@{u}..' );

		return $commits;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return mixed
	 */
	public function get_behind_commits() {
		list( , $commits ) = $this->_call( 'rev-list', '..@{u}' );

		return $commits;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param $url
	 *
	 * @return bool
	 */
	public function add_remote_url( $url ): bool {
		list( $return, ) = $this->_call( 'remote', 'add', 'origin', $url );

		return ( 0 === $return );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_remote_url(): string {
		list( , $response ) = $this->_call( 'config', '--get', 'remote.origin.url' );

		return $response[0] ?? '';
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function remove_remote(): bool {
		list( $return, ) = $this->_call( 'remote', 'rm', 'origin' );

		return ( 0 == $return );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return false|string
	 */
	public function get_remote_tracking_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}' );
		if ( 0 === $return ) {
			return $response[0];
		}

		return false;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return false|string
	 */
	function get_local_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', 'HEAD' );
		if ( 0 === $return ) {
			return $response[0];
		}

		return false;
	}

	/**
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	function fetch_ref(): bool {
		list( $return, ) = $this->_call( 'fetch', 'origin' );

		return ( 0 === $return );
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $commit Commit hash.
	 *
	 * @return false|string
	 */
	public function get_commit_message( string $commit ) {
		list( $return, $response ) = $this->_call( 'log', '--format=%B', '-n', '1', $commit );

		return ( $return !== 0 ? false : join( "\n", $response ) );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return array
	 */
	function get_remote_branches(): array {
		list( , $response ) = $this->_call( 'branch', '-r' );

		return array_map( function ( $b ) {
			return str_replace( 'origin/', '', trim( $b ) );
		}, $response );
	}

	/**
	 * @since 0.10.0
	 *
	 * @param ...$args
	 *
	 * @return int|void
	 */
	public function add( ...$args ) {
		if ( 1 == count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$params = array_merge( [ 'add', '-n', '--all' ], $args );
		list ( , $response ) = call_user_func_array( [ $this, '_call' ], $params );
		$count = count( $response );

		$params = array_merge( [ 'add', '--all' ], $args );
		list ( , $response ) = call_user_func_array( [ $this, '_call' ], $params );

		return $count;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $message
	 * @param string $author
	 *
	 * @return false|string Commit hash on success. False on failure.
	 */
	public function commit( string $message, string $author = '' ) {
		if ( ! empty( $author ) ) {
			list( $return, $response ) = $this->_call( 'commit', '-m', $message, '--author', $author );
		} else {
			list( $return, $response ) = $this->_call( 'commit', '-m', $message );
		}
		if ( $return !== 0 ) {
			return false;
		}

		list( $return, $response ) = $this->_call( 'rev-parse', 'HEAD' );

		return ( $return === 0 ) ? $response[0] : false;
	}

	/**
	 * @since 0.10.0
	 *
	 * @param string $branch
	 *
	 * @return bool
	 */
	public function push( string $branch = '' ): bool {
		if ( ! empty( $branch ) ) {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', $branch );
		} else {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', 'HEAD' );
		}

		return ( $return === 0 );
	}

	/**
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function local_status(): array {
		$branch_status = '';
		$new_response  = [];

		list( $return, $response ) = $this->_call( 'status', '-s', '-b', '-u' );
		if ( 0 !== $return ) {
			return [ $branch_status, $new_response ];
		}

		if ( ! empty( $response ) ) {
			$branch_status = array_shift( $response );
			foreach ( $response as $idx => $line ) {
				unset( $index_status, $work_tree_status, $path, $new_path, $old_path );

				// Ignore empty lines like the last item.
				if ( empty( $line ) ) {
					continue;
				}

				// Ignore branch status.
				if ( '#' == $line[0] ) {
					continue;
				}

				$index_status     = substr( $line, 0, 1 );
				$work_tree_status = substr( $line, 1, 1 );
				$path             = substr( $line, 3 );

				$old_path = '';
				$new_path = explode( '->', $path );
				if ( ( 'R' === $index_status ) && ( ! empty( $new_path[1] ) ) ) {
					$old_path = trim( $new_path[0] );
					$path     = trim( $new_path[1] );
				}
				$new_response[ $path ] = trim( $index_status . $work_tree_status . ' ' . $old_path );
			}
		}

		return [ $branch_status, $new_response ];
	}

	/**
	 * @since 0.10.0
	 *
	 * @param false $local_only
	 *
	 * @return array
	 */
	public function status( bool $local_only = false ): array {
		list( $branch_status, $new_response ) = $this->local_status();

		if ( $local_only ) {
			return [ $branch_status, $new_response ];
		}

		$behind_count = 0;
		$ahead_count  = 0;
		if ( preg_match( '/## ([^.]+)\.+([^ ]+)/', $branch_status, $matches ) ) {
			$local_branch  = $matches[1];
			$remote_branch = $matches[2];

			list( , $response ) = $this->_call( 'rev-list', "$local_branch..$remote_branch", '--count' );
			$behind_count = (int) $response[0];

			list( , $response ) = $this->_call( 'rev-list', "$remote_branch..$local_branch", '--count' );
			$ahead_count = (int) $response[0];
		}

		if ( $behind_count ) {
			list( , $response ) = $this->_call( 'diff', '-z', '--name-status', "$local_branch~$ahead_count", $remote_branch );
			$response = explode( chr( 0 ), $response[0] );
			array_pop( $response );
			for ( $idx = 0; $idx < count( $response ) / 2; $idx ++ ) {
				$file   = $response[ $idx * 2 + 1 ];
				$change = $response[ $idx * 2 ];
				if ( ! isset( $new_response[ $file ] ) ) {
					$new_response[ $file ] = "r$change";
				}
			}
		}

		return [ $branch_status, $new_response ];
	}

	/**
	 * Remove files in $path from version control index tree.
	 *
	 * @since 0.10.0
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	function rm_cached( string $path ): bool {
		list( $return, ) = $this->_call( 'rm', '--cached', $path );

		return ( $return === 0 );
	}
}
