<?php
/**
 * Plugin service definitions.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor;

use Cedaro\WP\Plugin\Provider\I18n;
use Env\Env;
use Pimple\Container as PimpleContainer;
use Pimple\Psr11\ServiceLocator;
use Pimple\ServiceIterator;
use Pimple\ServiceProviderInterface;
use PixelgradeLT\Conductor\Exception\PixelgradeltConductorException;
use PixelgradeLT\Conductor\Logging\Handler\FileLogHandler;
use PixelgradeLT\Conductor\Logging\Logger;
use PixelgradeLT\Conductor\Logging\LogsManager;
use Psr\Log\LogLevel;
use PixelgradeLT\Conductor\HTTP\Request;
use PixelgradeLT\Conductor\Provider;

/**
 * Plugin service provider class.
 *
 * @since 0.1.0
 */
class ServiceProvider implements ServiceProviderInterface {
	/**
	 * Register services.
	 *
	 * @param PimpleContainer $container Container instance.
	 */
	public function register( PimpleContainer $container ) {

		$container['client.composer'] = function ( $container ) {
			return new Client\ComposerClient(
				$container['storage.composer_working_directory']
			);
		};

		$container['client.composer.custom_token_auth'] = function () {
			return new Client\CustomTokenAuthentication();
		};

		$container['hooks.activation'] = function () {
			return new Provider\Activation();
		};

		$container['hooks.admin_assets'] = function () {
			return new Provider\AdminAssets();
		};

		$container['hooks.deactivation'] = function () {
			return new Provider\Deactivation();
		};

		$container['hooks.health_check'] = function ( $container ) {
			return new Provider\HealthCheck(
				$container['http.request']
			);
		};

		$container['hooks.i18n'] = function () {
			return new I18n();
		};

		$container['hooks.request_handler'] = function ( $container ) {
			return new Provider\RequestHandler(
				$container['http.request'],
				$container['route.controllers']
			);
		};

		$container['hooks.rewrite_rules'] = function () {
			return new Provider\RewriteRules();
		};

		$container['hooks.upgrade'] = function ( $container ) {
			return new Provider\Upgrade(
				$container['htaccess.handler'],
				$container['logs.logger']
			);
		};

		$container['htaccess.handler'] = function ( $container ) {
			return new Htaccess( $container['storage.working_directory'] );
		};

		$container['http.request'] = function () {
			$request = new Request( $_SERVER['REQUEST_METHOD'] ?? '' );

			// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
			$request->set_query_params( wp_unslash( $_GET ) );
			$request->set_header( 'Authorization', get_authorization_header() );

			return $request;
		};

		$container['logs.logger'] = function ( $container ) {
			return new Logger(
				$container['logs.level'],
				[
					$container['logs.handlers.file'],
				]
			);
		};

		$container['logs.level'] = function () {
			// Log warnings and above when WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$level = LogLevel::WARNING;
			}

			return $level ?? '';
		};

		$container['logs.handlers.file'] = function () {
			return new FileLogHandler();
		};

		$container['logs.manager'] = function ( $container ) {
			return new LogsManager( $container['logs.logger'] );
		};

		$container['route.composer.solutions'] = function ( $container ) {
			return new Route\ComposerPackages(
				$container['repository.solutions'],
				$container['transformer.composer_repository']
			);
		};

		$container['route.controllers'] = function ( $container ) {
			return new ServiceLocator(
				$container,
				[
					'composer_solutions' => 'route.composer.solutions',
				]
			);
		};

		$container['storage.working_directory'] = function ( $container ) {
			if ( \defined( 'PixelgradeLT\Conductor\WORKING_DIRECTORY' ) ) {
				return \PixelgradeLT\Conductor\WORKING_DIRECTORY;
			}

			$path = \get_temp_dir();

			return trailingslashit( apply_filters( 'pixelgradelt_conductor/working_directory', $path ) );
		};

		$container['storage.composer_working_directory'] = function ( $container ) {
			return \path_join( $container['storage.working_directory'], 'composer/' );
		};
	}
}
