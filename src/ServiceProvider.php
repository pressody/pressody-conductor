<?php
/**
 * Plugin service definitions.
 *
 * @since   0.1.0
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

declare ( strict_types=1 );

namespace Pressody\Conductor;

use Cedaro\WP\Plugin\Provider\I18n;
use Pimple\Container as PimpleContainer;
use Pimple\Psr11\ServiceLocator;
use Pimple\ServiceProviderInterface;
use Pressody\Conductor\Cache\CacheManager;
use Pressody\Conductor\Composition\CompositionManager;
use Pressody\Conductor\Git\GitClient;
use Pressody\Conductor\Git\GitManager;
use Pressody\Conductor\Git\GitRepo;
use Pressody\Conductor\Git\GitWrapper;
use Pressody\Conductor\Logging\ComposerLogger;
use Pressody\Conductor\Logging\Handler\CLILogHandler;
use Pressody\Conductor\Logging\Handler\FileLogHandler;
use Pressody\Conductor\Logging\Logger;
use Pressody\Conductor\Logging\LogsManager;
use Psr\Log\LogLevel;
use Pressody\Conductor\HTTP\Request;
use Pressody\Conductor\Provider;

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

		$container['cache.manager'] = function ( $container ) {
			return new CacheManager(
				$container['queue.action'],
				$container['logger.main']
			);
		};

		$container['cli.cache.manager'] = function ( $container ) {
			return new CacheManager(
				$container['queue.action'],
				$container['logger.cli']
			);
		};

		$container['composer.wrapper'] = function ( $container ) {
			return new Composer\ComposerWrapper(
				$container['logger.composer']
			);
		};

		$container['cli.composer.wrapper'] = function ( $container ) {
			return new Composer\ComposerWrapper(
				$container['logger.composer.cli']
			);
		};

		$container['composition.manager'] = function ( $container ) {
			return new CompositionManager(
				$container['queue.action'],
				$container['logger.main'],
				$container['composer.wrapper']
			);
		};

		$container['cli.composition.manager'] = function ( $container ) {
			return new CompositionManager(
				$container['queue.action'],
				$container['logger.cli'],
				$container['cli.composer.wrapper']
			);
		};

		$container['git.client'] = function ( $container ) {
			return new GitClient(
				$container['git.repo'],
				$container['logger.main']
			);
		};

		$container['git.manager'] = function ( $container ) {
			return new GitManager(
				$container['git.client'],
				$container['composition.manager'],
				$container['queue.action'],
				$container['logger.main']
			);
		};

		$container['git.repo'] = function ( $container ) {
			return new GitRepo(
				\PD_ROOT_DIR,
				$container['git.wrapper'],
				$container['logger.main']
			);
		};

		$container['git.wrapper'] = function ( $container ) {
			return new GitWrapper(
				\PD_ROOT_DIR,
				$container['logger.main']
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
				$container['logger.main']
			);
		};

		$container['hooks.wpupdates'] = function ( $container ) {
			return new Provider\WPUpdates(
				$container['composition.manager']
			);
		};

		$container['http.request'] = function () {
			$request = new Request( $_SERVER['REQUEST_METHOD'] ?? '' );

			// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
			$request->set_query_params( wp_unslash( $_GET ) );
			$request->set_header( 'Authorization', get_authorization_header() );

			return $request;
		};

		$container['logger.main'] = function ( $container ) {
			return new Logger(
				$container['logs.level'],
				[
					$container['logs.handler.file'],
				]
			);
		};

		$container['logger.cli'] = function ( $container ) {
			return new Logger(
				LogLevel::INFO,
				[
					// Use both loggers to display in the CLI and log into the log files.
					// The CLI handler is a pass-through handler.
					$container['logs.handler.cli'],
					$container['logs.handler.file'],
				]
			);
		};

		$container['logger.composer'] = function ( $container ) {
			return new ComposerLogger(
				$container['logs.level'],
				[
					$container['logs.handler.file'],
				]
			);
		};

		$container['logger.composer.cli'] = function ( $container ) {
			return new ComposerLogger(
				LogLevel::INFO,
				[
					// Use both loggers to display in the CLI and log into the log files.
					// The CLI handler is a pass-through handler.
					$container['logs.handler.cli'],
					$container['logs.handler.file'],
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

		$container['logs.handler.file'] = function () {
			return new FileLogHandler();
		};

		$container['logs.handler.cli'] = function () {
			return new CLILogHandler();
		};

		$container['logs.manager'] = function ( $container ) {
			return new LogsManager(
				$container['logger.main'],
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

		$container['screen.plugins'] = function ( $container ) {
			return new Screen\Plugins(
				$container['composition.manager']
			);
		};

		$container['screen.settings'] = function () {
			return new Screen\Settings();
		};

		$container['screen.themes'] = function ( $container ) {
			return new Screen\Themes(
				$container['composition.manager']
			);
		};

		$container['screen.update-core'] = function () {
			return new Screen\UpdateCore();
		};
	}
}
