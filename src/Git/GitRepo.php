<?php
/**
 * Git repository interaction.
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Git;

use Psr\Log\LoggerInterface;

/**
 * Git repository interaction class.
 *
 * @since   0.10.0
 * @package PixelgradeLT
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
	 * @return bool
	 */
	public function can_interact(): bool {
		return $this->git_wrapper->can_exec_git() && $this->git_wrapper->is_status_working();
	}

	/**
	 * Get the installed git version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->git_wrapper->get_version();
	}

	/**
	 * Checks if we have an actual Git repo.
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
	 * @return array
	 */
	public function get_local_changes(): array {
		list( $return, $response ) = $this->git_wrapper->_call( 'status', '--porcelain' );

		if ( 0 !== $return ) {
			return [];
		}
		$new_response = [];
		if ( ! empty( $response ) ) {
			foreach ( $response as $line ) :
				$work_tree_status = substr( $line, 1, 1 );
				$path             = substr( $line, 3 );

				if ( ( '"' == $path[0] ) && ( '"' == $path[ strlen( $path ) - 1 ] ) ) {
					// git status --porcelain will put quotes around paths with whitespaces
					// we don't want the quotes, let's get rid of them
					$path = substr( $path, 1, strlen( $path ) - 2 );
				}

				if ( 'D' == $work_tree_status ) {
					$action = 'deleted';
				} else {
					$action = 'modified';
				}
				$new_response[ $path ] = $action;
			endforeach;
		}

		return $new_response;
	}

	/**
	 * @return mixed
	 */
	public function get_uncommitted_changes() {
		list( , $changes ) = $this->git_wrapper->status();

		return $changes;
	}

	/**
	 * @return array
	 */
	public function local_status(): array {
		return $this->git_wrapper->local_status();
	}

	/**
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
	 * @return bool
	 */
	public function is_dirty(): bool {
		$changes = $this->get_uncommitted_changes();

		return ! empty( $changes );
	}

	/**
	 * Add a remote URL for origin.
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
	 * @return string The remote URL. Empty string on missing remote URL.
	 */
	public function get_remote_url(): string {
		$this->git_wrapper->get_remote_url();
	}

	/**
	 * Remove the repo's remote origin setting.
	 *
	 * @return bool
	 */
	public function remove_remote(): bool {
		return $this->git_wrapper->remove_remote();
	}

	/**
	 * @param string $commit
	 *
	 * @return string|false
	 */
	public function get_commit_message( string $commit ) {
		return $this->git_wrapper->get_commit_message( $commit );
	}

	/**
	 * Return the last n commits
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
	 * @param string $content
	 *
	 * @return bool
	 */
	public function set_gitignore( string $content ): bool {
		return false !== file_put_contents( $this->repo_dir . '/.gitignore', $content );
	}

	/**
	 * @return string|false
	 */
	public function get_gitignore() {
		return file_get_contents( $this->repo_dir . '/.gitignore' );
	}

	/**
	 * Remove files version control index.
	 */
	public function rm_cached( $path ): bool {
		return $this->git_wrapper->rm_cached( $path );
	}

	/**
	 * @return bool
	 */
	public function fetch_ref(): bool {
		return $this->git_wrapper->fetch_ref();
	}

	/**
	 * @param ...$commits
	 *
	 * @return bool
	 */
	public function merge_with_accept_mine( ...$commits ): bool {
		do_action( 'gitium_before_merge_with_accept_mine' );

		if ( 1 == count( $commits ) && is_array( $commits[0] ) ) {
			$commits = $commits[0];
		}

		// get ahead commits
		$ahead_commits = $this->git_wrapper->get_ahead_commits();

		// combine all commits with the ahead commits
		$commits = array_unique( array_merge( array_reverse( $commits ), $ahead_commits ) );
		$commits = array_reverse( $commits );

		// get the remote branch
		$remote_branch = $this->git_wrapper->get_remote_tracking_branch();

		// get the local branch
		$local_branch = $this->git_wrapper->get_local_branch();

		// rename the local branch to 'merge_local'
		$this->git_wrapper->_call( 'branch', '-m', 'merge_local' );

		// local branch set up to track remote branch
		$this->git_wrapper->_call( 'branch', $local_branch, $remote_branch );

		// checkout to the $local_branch
		list( $return, ) = $this->git_wrapper->_call( 'checkout', $local_branch );
		if ( $return != 0 ) {
			$this->git_wrapper->_call( 'branch', '-M', $local_branch );

			return false;
		}

		// don't cherry pick if there are no commits
		if ( count( $commits ) > 0 ) {
			$this->cherry_pick_commits( $commits );
		}

		if ( $this->successfully_merged() ) { // git status without states: AA, DD, UA, AU ...
			// delete the 'merge_local' branch
			$this->git_wrapper->_call( 'branch', '-D', 'merge_local' );

			return true;
		} else {
			$this->git_wrapper->_call( 'cherry-pick', '--abort' );
			$this->git_wrapper->_call( 'checkout', '-b', 'merge_local' );
			$this->git_wrapper->_call( 'branch', '-M', $local_branch );

			return false;
		}
	}

	protected function cherry_pick_commits( array $commits ) {
		foreach ( $commits as $commit ) {
			if ( empty( $commit ) ) {
				return false;
			}

			list( $return, $response ) = $this->git_wrapper->_call( 'cherry-pick', $commit );

			// abort the cherry-pick if the changes are already pushed
			if ( false !== $this->strpos_haystack_array( $response, 'previous cherry-pick is now empty' ) ) {
				$this->git_wrapper->_call( 'cherry-pick', '--abort' );
				continue;
			}

			if ( $return != 0 ) {
				$this->resolve_merge_conflicts( $this->get_commit_message( $commit ) );
			}
		}
	}

	protected function strpos_haystack_array( $haystack, $needle, $offset = 0 ) {
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

	protected function resolve_merge_conflicts( $message ) {
		list( , $changes ) = $this->status( true );
		// @todo Maybe log.
		foreach ( $changes as $path => $change ) {
			if ( in_array( $change, [ 'UD', 'DD' ] ) ) {
				$this->git_wrapper->_call( 'rm', $path );
				$message .= "\n\tConflict: $path [removed]";
			} elseif ( 'DU' == $change ) {
				$this->git_wrapper->_call( 'add', $path );
				$message .= "\n\tConflict: $path [added]";
			} elseif ( in_array( $change, [ 'AA', 'UU', 'AU', 'UA' ] ) ) {
				$this->git_wrapper->_call( 'checkout', '--theirs', $path );
				$this->git_wrapper->_call( 'add', '--all', $path );
				$message .= "\n\tConflict: $path [local version]";
			}
		}
		$this->commit( $message );
	}

	protected function successfully_merged(): bool {
		list( , $response ) = $this->status( true );
		$changes = array_values( $response );

		return ( 0 == count( array_intersect( $changes, [ 'DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU' ] ) ) );
	}

	/**
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
	 * @param string $branch
	 *
	 * @return bool
	 */
	public function push( string $branch = '' ): bool {
		return $this->git_wrapper->push( $branch );
	}
}
