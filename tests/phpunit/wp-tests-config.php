<?php
/**
 * Configuration for the WordPress testing suite.
 *
 * @package   PixelgradeLT\Conductor\Tests
 * @copyright Copyright (c) 2019 Cedaro, LLC
 * @license   MIT
 */

declare ( strict_types = 1 );

/**
 * LOAD OUR TEST ENVIRONMENT VARIABLES FROM .ENV
 */
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
// We use immutable since we don't want to overwrite variables already set.
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['WP_TESTS_DB_NAME', 'WP_TESTS_DB_USER', 'WP_TESTS_DB_PASSWORD', 'WP_TESTS_DB_HOST']);
// Read environment variables from the $_ENV array also.
\Env\Env::$options |= \Env\Env::USE_ENV_ARRAY;

/**
 * PROCEED
 */

// Path to the WordPress codebase to test. Add a forward slash in the end.
define( 'ABSPATH', realpath( dirname( __DIR__, 2 ) . '/vendor/wordpress/wordpress/src' ) . '/' );

// Path to the theme to test with.
define( 'WP_DEFAULT_THEME', 'default' );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** MySQL settings ** //

// This configuration file will be used by the copy of WordPress being tested.
// wordpress/wp-config.php will be ignored.

// WARNING WARNING WARNING!
// These tests will DROP ALL TABLES in the database with the prefix named below.
// DO NOT use a production database or one that is shared with something else.

define( 'DB_NAME', \Env\Env::get( 'WP_TESTS_DB_NAME' ) !== null ? \Env\Env::get( 'WP_TESTS_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', \Env\Env::get( 'WP_TESTS_DB_USER' ) !== null ? \Env\Env::get( 'WP_TESTS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', \Env\Env::get( 'WP_TESTS_DB_PASSWORD' ) !== null ? \Env\Env::get( 'WP_TESTS_DB_PASSWORD' ) : '' );
define( 'DB_HOST', \Env\Env::get( 'WP_TESTS_DB_HOST' ) !== null ? \Env\Env::get( 'WP_TESTS_DB_HOST' ) : 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';   // Only numbers, letters, and underscores!

// Test suite configuration.
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
