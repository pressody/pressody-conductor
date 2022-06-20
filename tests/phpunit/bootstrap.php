<?php
declare ( strict_types = 1 );

use Pressody\Conductor\Tests\Framework\PHPUnitUtil;
use Pressody\Conductor\Tests\Framework\TestSuite;
use Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'Pressody\Conductor\RUNNING_UNIT_TESTS', true );
define( 'Pressody\Conductor\TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [ 'pressody-conductor/pressody-conductor.php' ],
	'timezone_string' => 'Europe/Bucharest',
];

$suite->addFilter( 'muplugins_loaded', function() {
	require dirname( __DIR__, 2 ) . '/pressody-conductor.php';
} );

$suite->addFilter( 'pressody_conductor/compose', function( $plugin, $container ) {
	$container['logger'] = new NullLogger();
	$container['storage.working_directory'] = \Pressody\Conductor\TESTS_DIR . '/Fixture/wp-content/uploads/pressody-conductor/';
}, 10, 2 );

$suite->bootstrap();
