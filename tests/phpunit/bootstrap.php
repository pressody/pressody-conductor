<?php
declare ( strict_types = 1 );

use PixelgradeLT\Conductor\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Conductor\Tests\Framework\TestSuite;
use Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'PixelgradeLT\Conductor\RUNNING_UNIT_TESTS', true );
define( 'PixelgradeLT\Conductor\TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [ 'pixelgradelt-conductor/pixelgradelt-conductor.php' ],
	'timezone_string' => 'Europe/Bucharest',
];

$suite->addFilter( 'muplugins_loaded', function() {
	require dirname( __DIR__, 2 ) . '/pixelgradelt-conductor.php';
} );

$suite->addFilter( 'pixelgradelt_conductor/compose', function( $plugin, $container ) {
	$container['logger'] = new NullLogger();
	$container['storage.working_directory'] = \PixelgradeLT\Conductor\TESTS_DIR . '/Fixture/wp-content/uploads/pixelgradelt-conductor/';
}, 10, 2 );

$suite->bootstrap();
