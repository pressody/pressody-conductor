<?php
/**
 * Capabilities.
 *
 * Meta capabilities are mapped to primitive capabilities in
 * \Pressody\Retailer\Provider\Capabilities.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor;

/**
 * Capabilities.
 *
 * @since 0.1.0
 */
final class Capabilities {

	/**
	 * Primitive capability for managing PD Conductor options.
	 *
	 * @var string
	 */
	const MANAGE_OPTIONS = 'pressody_conductor_manage_options';

	/**
	 * Primitive capability for updating composition.
	 *
	 * @var string
	 */
	const UPDATE_COMPOSITION = 'pressody_conductor_update_composition';

	/**
	 * Primitive capability for updating composition.
	 *
	 * @var string
	 */
	const SUPPORT_ROLE = 'pressody_conductor_support';

	/**
	 * Register roles and capabilities.
	 *
	 * @since 0.1.0
	 */
	public static function register() {
		$wp_roles = wp_roles();

		// Create a special role for users intended to be used by the Pressody support crew.
		// First remove it to be able to overwrite it in case it already exists.
		$wp_roles->remove_role( self::SUPPORT_ROLE );
		$wp_roles->add_role( self::SUPPORT_ROLE, 'PD Support', [
			// The PD Conductor specific capabilities.
			self::MANAGE_OPTIONS    => true,
			self::UPDATE_COMPOSITION    => true,
			// WordPress core capabilities. We try to be as strict as possible.
			'moderate_comments' => false,
			'manage_categories' => false,
			'manage_links' => false,
			'unfiltered_html' => true,

			'read' => true,

			'edit_pages' => true,
			'edit_others_pages' => true,
			'edit_published_pages' => true,
			'publish_pages' => false,
			'delete_pages' => false,
			'delete_others_pages' => false,
			'delete_published_pages' => false,
			'delete_private_pages' => false,
			'edit_private_pages' => false,
			'read_private_pages' => true,

			'edit_posts' => true,
			'edit_others_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'delete_posts' => false,
			'delete_others_posts' => false,
			'delete_published_posts' => false,
			'delete_private_posts' => false,
			'edit_private_posts' => false,
			'read_private_posts' => true,

			'unfiltered_upload' => true,
			'edit_dashboard' => true,

			'activate_plugins' => true,
			'edit_plugins' => false,
			'update_plugins' => true,
			'delete_plugins' => true,
			'install_plugins' => true,

			'switch_themes' => true,
			'edit_themes' => false,
			'update_themes' => true,
			'install_themes' => true,
			'delete_themes' => false,
			'edit_theme_options' => true,

			'update_core' => false,

			'edit_users' => false,
			'list_users' => true,
			'remove_users' => false,
			'promote_users' => false,
			'delete_users' => false,
			'create_users' => false,

			'edit_files' => false,
			'upload_files' => false,

			'manage_options' => true,

			'export' => true,
			'import' => false,

			'view_site_health_checks' => true,

			// Provide the needed User Role Editor plugin capabilities.
			'ure_manage_options' => true,
			'ure_edit_roles' => true,

			'level_10' => true,
			'level_9' => true,
			'level_8' => true,
			'level_7' => true,
			'level_6' => true,
			'level_5' => true,
			'level_4' => true,
			'level_3' => true,
			'level_2' => true,
			'level_1' => true,
			'level_0' => true,
		] );

		// Handle the administrator core role.
		$wp_roles->remove_cap( 'administrator', 'view_site_health_checks' );
		$wp_roles->remove_cap( 'administrator', 'update_core' );
	}
}
