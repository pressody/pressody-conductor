<?php
/**
 * Git command wrapper.
 *
 * Borrowed (and modified) from Gitium: https://github.com/presslabs/gitium/blob/master/gitium/inc/class-git-wrapper.php
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Git;

class GitWrapper {

	private $last_error = '';
	private $repo_dir = '';

	function __construct( $repo_dir ) {
		$this->repo_dir    = $repo_dir;
		$this->private_key = '';
	}

	function _git_temp_key_file() {
		$key_file = tempnam( sys_get_temp_dir(), 'ssh-git' );

		return $key_file;
	}

	function set_key( $private_key ) {
		$this->private_key = $private_key;
	}

	private function get_env() {
		$env      = array();
		$key_file = null;

		if ( defined( 'GIT_SSH' ) && GIT_SSH ) {
			$env['GIT_SSH'] = GIT_SSH;
		} else {
			$env['GIT_SSH'] = dirname( __FILE__ ) . '/ssh-git';
		}

		if ( defined( 'GIT_KEY_FILE' ) && GIT_KEY_FILE ) {
			$env['GIT_KEY_FILE'] = GIT_KEY_FILE;
		} elseif ( $this->private_key ) {
			$key_file = $this->_git_temp_key_file();
			chmod( $key_file, 0600 );
			file_put_contents( $key_file, $this->private_key );
			$env['GIT_KEY_FILE'] = $key_file;
		}

		return $env;
	}

	public function _call( ...$args ) {
		$args     = join( ' ', array_map( 'escapeshellarg', $args ) );
		$cmd      = "git $args 2>&1";
		$return   = - 1;
		$response = array();
		$env      = $this->get_env();

		$proc = proc_open(
			$cmd,
			array(
				0 => array( 'pipe', 'r' ),  // stdin
				1 => array( 'pipe', 'w' ),  // stdout
			),
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
		}

		if ( ! defined( 'GIT_KEY_FILE' ) && isset( $env['GIT_KEY_FILE'] ) ) {
			unlink( $env['GIT_KEY_FILE'] );
		}
		if ( 0 != $return ) {
			$this->last_error = join( "\n", $response );
		} else {
			$this->last_error = null;
		}

		return array( $return, $response );
	}

	public function get_last_error() {
		return $this->last_error;
	}

	public function can_exec_git() {
		list( $return, ) = $this->_call( 'version' );

		return ( 0 == $return );
	}

	public function is_status_working() {
		list( $return, ) = $this->_call( 'status', '-s' );

		return ( 0 == $return );
	}

	public function get_version() {
		list( $return, $version ) = $this->_call( 'version' );
		if ( 0 != $return ) {
			return '';
		}
		if ( ! empty( $version[0] ) ) {
			return substr( $version[0], 12 );
		}

		return '';
	}

	// git rev-list @{u}..
	public function get_ahead_commits() {
		list( , $commits ) = $this->_call( 'rev-list', '@{u}..' );

		return $commits;
	}

	// git rev-list ..@{u}
	public function get_behind_commits() {
		list( , $commits ) = $this->_call( 'rev-list', '..@{u}' );

		return $commits;
	}

	public function add_remote_url( $url ) {
		list( $return, ) = $this->_call( 'remote', 'add', 'origin', $url );

		return ( 0 == $return );
	}

	public function get_remote_url() {
		list( , $response ) = $this->_call( 'config', '--get', 'remote.origin.url' );
		if ( isset( $response[0] ) ) {
			return $response[0];
		}

		return '';
	}

	public function remove_remote() {
		list( $return, ) = $this->_call( 'remote', 'rm', 'origin' );

		return ( 0 == $return );
	}

	public function get_remote_tracking_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}' );
		if ( 0 == $return ) {
			return $response[0];
		}

		return false;
	}

	function get_local_branch() {
		list( $return, $response ) = $this->_call( 'rev-parse', '--abbrev-ref', 'HEAD' );
		if ( 0 == $return ) {
			return $response[0];
		}

		return false;
	}

	function fetch_ref() {
		list( $return, ) = $this->_call( 'fetch', 'origin' );

		return ( 0 == $return );
	}

	public function get_commit_message( $commit ) {
		list( $return, $response ) = $this->_call( 'log', '--format=%B', '-n', '1', $commit );

		return ( $return !== 0 ? false : join( "\n", $response ) );
	}

	function get_remote_branches() {
		list( , $response ) = $this->_call( 'branch', '-r' );
		$response = array_map( 'trim', $response );
		$response = array_map( create_function( '$b', 'return str_replace("origin/","",$b);' ), $response );

		return $response;
	}

	public function add( ...$args ) {
		if ( 1 == count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$params = array_merge( array( 'add', '-n', '--all' ), $args );
		list ( , $response ) = call_user_func_array( array( $this, '_call' ), $params );
		$count = count( $response );

		$params = array_merge( array( 'add', '--all' ), $args );
		list ( , $response ) = call_user_func_array( array( $this, '_call' ), $params );

		return $count;
	}

	public function commit( $message, $author = '' ) {
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

	public function push( $branch = '' ) {
		if ( ! empty( $branch ) ) {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', $branch );
		} else {
			list( $return, ) = $this->_call( 'push', '--porcelain', '-u', 'origin', 'HEAD' );
		}

		return ( $return == 0 );
	}

	public function local_status() {
		list( $return, $response ) = $this->_call( 'status', '-s', '-b', '-u' );
		if ( 0 !== $return ) {
			return array( '', array() );
		}

		$new_response = array();
		if ( ! empty( $response ) ) {
			$branch_status = array_shift( $response );
			foreach ( $response as $idx => $line ) :
				unset( $index_status, $work_tree_status, $path, $new_path, $old_path );

				if ( empty( $line ) ) {
					continue;
				} // ignore empty lines like the last item
				if ( '#' == $line[0] ) {
					continue;
				} // ignore branch status

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
			endforeach;
		}

		return array( $branch_status, $new_response );
	}

	public function status( $local_only = false ) {
		list( $branch_status, $new_response ) = $this->local_status();

		if ( $local_only ) {
			return array( $branch_status, $new_response );
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

		return array( $branch_status, $new_response );
	}

	/**
	 * Remove files in $path from version control index tree.
	 */
	function rm_cached( $path ) {
		list( $return, ) = $this->_call( 'rm', '--cached', $path );

		return ( $return == 0 );
	}
}
