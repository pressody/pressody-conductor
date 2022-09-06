<?php
/**
 * Views: Settings tab content.
 *
 * @since   1.0.0
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

declare ( strict_types = 1 );

namespace Pressody\Conductor;

$allowed_tags = [
		'a'    => [
				'href' => true,
		],
		'em'   => [],
		'strong'   => [],
		'code' => [],
];
?>
<div class="pressody_conductor-card">
	<p>
		<?php echo wp_kses( __( 'These are a series settings and controls to help you with edge-cases around your Pressody experience.', 'pressody_conductor' ), $allowed_tags ); ?>
	</p>
</div>

<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
	<?php settings_fields( 'pressody_conductor' ); ?>
	<?php do_settings_sections( 'pressody_conductor' ); ?>
	<?php submit_button(); ?>
</form>
