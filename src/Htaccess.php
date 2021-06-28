<?php
/**
 * Htaccess class
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor;

/**
 * Interact with the .htaccess file.
 *
 * @since 0.1.0
 */
class Htaccess {
	/**
	 * The directory path where .htaccess is located.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $path = '';

	/**
	 * .htaccess rules.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $rules = [];

	/**
	 * Constructor method.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $path Optional. Directory path where .htaccess is located. Default is empty string.
	 */
	public function __construct( string $path = null ) {
		if ( null === $path ) {
			$path = '';
		}

		$this->path = $path;
	}

	/**
	 * Add rules to .htaccess.
	 *
	 * @since 0.1.0
	 *
	 * @param array $rules List of rules to add.
	 */
	public function add_rules( array $rules ) {
		$this->rules = array_merge( $this->rules, $rules );
	}

	/**
	 * Retrieve the full path to the .htaccess file itself.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_file(): string {
		return $this->path . '.htaccess';
	}

	/**
	 * Retrieve the rules in the .htaccess file.
	 *
	 * Only contains the rules between the #PixelgradeLT Conductor delimiters.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_rules(): array {
		return (array) apply_filters( 'pixelgradelt_conductor/htaccess_rules', $this->rules );
	}

	/**
	 * Determine if the .htaccess file exists.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if the file exists, false otherwise.
	 */
	public function file_exists(): bool {
		return file_exists( $this->get_file() );
	}

	/**
	 * Determine if the .htaccess file is writable.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_writable(): bool {
		$file = $this->get_file();

		return ( ! $this->file_exists() && is_writable( $this->path ) ) || is_writable( $file );
	}

	/**
	 * Save rules to the .htaccess file.
	 *
	 * @since 0.1.0
	 */
	public function save() {
		$rules = $this->get_rules();
		insert_with_markers( $this->get_file(), 'PixelgradeLT Conductor', $rules );
	}
}
