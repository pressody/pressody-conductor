<?php
/**
 * Composer wrapper interface.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.8.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Composer;

/**
 * Client interface.
 *
 * @package PixelgradeLT
 * @since 0.8.0
 */
interface ComposerWrapperInterface {

	/**
	 * Runs composer install.
	 *
	 * @param string $composer_json_path The absolute path to the composer.json to use.
	 * @param array  $args Various args to change Composer's behavior. These overwrite any configuration Composer determines by itself.
	 *                     Provide entry `revert_file_path` as an absolute path to a composer.json backup to revert to in case of errors.
	 *
	 * @return bool
	 */
	public function install( string $composer_json_path, array $args = [] ): bool;
}
