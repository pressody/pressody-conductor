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
use PixelgradeLT\Conductor\Composition\CompositionManager;
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

		$container['client.composer'] = function () {
			return new Client\ComposerClient(
				COMPOSER_DIR
			);
		};

		$container['client.composer.custom_token_auth'] = function () {
			return new Client\CustomTokenAuthentication();
		};

		$container['composition.manager'] = function ( $container ) {
			return new CompositionManager(
				$container['queue.action'],
				$container['logs.logger']
			);
		};

		$container['hooks.activation'] = function () {
			return new Provider\Activation();
		};

		$container['hooks.admin_assets'] = function () {
			return new Provider\AdminAssets();
		};

		$container['hooks.capabilities'] = function () {
			return new Provider\Capabilities();
		};

		$container['hooks.deactivation'] = function () {
			return new Provider\Deactivation();
		};

		$container['hooks.i18n'] = function () {
			return new I18n();
		};

		$container['hooks.maintenance'] = function () {
			return new Provider\Maintenance();
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
				$container['logs.logger']
			);
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
			// Log everything when WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$level = LogLevel::DEBUG;
			}

			// If WP_DEBUG is not enabled, we will still log everything from NOTICE messages and up.
			// Only INFO and DEBUG are not logged.
			return $level ?? LogLevel::NOTICE;
		};

		$container['logs.handlers.file'] = function () {
			return new FileLogHandler();
		};

		$container['logs.manager'] = function ( $container ) {
			return new LogsManager(
				$container['logs.logger'],
				$container['queue.action']
			);
		};

		$container['queue.action'] = function () {
			return new Queue\ActionQueue();
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

		$container['screen.settings'] = function () {
			return new Screen\Settings();
		};
	}
}
