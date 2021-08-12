<?php
/**
 * Views: Settings tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor;

$allowed_tags = [
		'a'    => [
				'href' => true,
		],
		'em'   => [],
		'strong'   => [],
		'code' => [],
];
?>
<div class="pixelgradelt_conductor-card">
	<p>
		<?php echo wp_kses( __( 'These are a series settings and controls to help you with edge-cases around your Pixelgrade LT experience.', 'pixelgradelt_conductor' ), $allowed_tags ); ?>
	</p>
</div>

<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
	<?php settings_fields( 'pixelgradelt_conductor' ); ?>
	<?php do_settings_sections( 'pixelgradelt_conductor' ); ?>
	<?php submit_button(); ?>
</form>
