<?php
/**
 * Views: Settings tab content.
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
		<?php echo wp_kses( __( 'These are a series settings and controls to help you with edge-cases around your Pressody experience.', 'pressody_conductor' ), $allowed_tags ); ?>
	</p>
</div>

<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
	<?php settings_fields( 'pressody_conductor' ); ?>
	<?php do_settings_sections( 'pressody_conductor' ); ?>
	<?php submit_button(); ?>
</form>
