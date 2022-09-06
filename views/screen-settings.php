<?php
/**
 * Views: Settings page
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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

declare ( strict_types = 1 );

namespace Pressody\Conductor;

/**
 * @global array $system_checks
 * @global string $active_tab
 * @global array $tabs
 */

?>

<div class="pressody_conductor-screen">
	<div class="pressody_conductor-screen-content wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $tabs as $tab_id => $tab_data ) {
				if ( ! current_user_can( $tab_data['capability'] ) ) {
					continue;
				}

				printf(
						'<a href="#pressody_conductor-%1$s" class="nav-tab%2$s">%3$s</a>',
						esc_attr( $tab_id ),
						$active_tab === $tab_id ? ' nav-tab-active' : '',
						esc_html( $tab_data['name'] )
				);
			}
			?>
		</h2>

		<?php
		foreach ( $tabs as $tab_id => $tab_data ) {
			if ( ! current_user_can( $tab_data['capability'] ) ) {
				continue;
			}

			printf(
					'<div id="pressody_conductor-%1$s" class="pressody_conductor-%1$s pressody_conductor-tab-panel%2$s">',
					esc_attr( $tab_id ),
					$active_tab === $tab_id ? ' is-active' : ''
			);

			require $this->plugin->get_path( "views/tabs/{$tab_id}.php" );

			echo '</div>';
		}
		?>
	</div>

	<div id="pressody_conductor-screen-sidebar" class="pressody_conductor-screen-sidebar"></div>
</div>
