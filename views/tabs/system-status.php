<?php
/**
 * Views: Access tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package Pressody
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
		<?php echo wp_kses( __( 'These are a series of system checks to reassure or warn you of <strong>how fit is the webserver for running Pressody Conductor.</strong>', 'pressody_conductor' ), $allowed_tags ); ?>
	</p>
</div>

<div class="pressody_conductor-card">
	<p>
		<?php echo wp_kses( __( 'None right now.', 'pressody_conductor' ), $allowed_tags ); ?>
	</p>
</div>

<div id="pressody_conductor-status"></div>
