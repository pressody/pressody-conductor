<?php
/**
 * Pressody Conductor request handler.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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

namespace Pressody\Conductor\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Psr\Container\ContainerInterface;
use Pressody\Conductor\Exception\AuthenticationException;
use Pressody\Conductor\HTTP\Request;
use Pressody\Conductor\HTTP\Response;
use Pressody\Conductor\Route\Route;
use WP;
use WP_REST_Server;
use function Pressody\Conductor\is_debug_mode;
use function Pressody\Conductor\is_running_unit_tests;

/**
 * Request handler class.
 *
 * @since 0.1.0
 */
class RequestHandler extends AbstractHookProvider {
	/**
	 * Route controllers.
	 *
	 * @var ContainerInterface
	 */
	protected ContainerInterface $controllers;

	/**
	 * Server request.
	 *
	 * @var Request
	 */
	protected Request $request;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Request            $request     Request instance.
	 * @param ContainerInterface $controllers Route controllers.
	 */
	public function __construct( Request $request, ContainerInterface $controllers ) {
		$this->request     = $request;
		$this->controllers = $controllers;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_action( 'parse_request', [ $this, 'dispatch' ] );
	}

	/**
	 * Dispatch the request to an endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param WP $wp Main WP instance.
	 * @throws \Exception If an exception is caught and debug mode is enabled.
	 */
	public function dispatch( WP $wp ) {
		if ( empty( $wp->query_vars['pressody_conductor_route'] ) ) {
			return;
		}

		$route = $wp->query_vars['pressody_conductor_route'];
		$this->request->set_route( $route );

		if ( ! empty( $wp->query_vars['pressody_conductor_params'] ) ) {
			$this->request->set_url_params( $wp->query_vars['pressody_conductor_params'] );
		}

		try {
			$response = $this->check_authentication();

			if ( null === $response ) {
				$controller = $this->get_route_controller( $route );
				$response   = $controller->handle( $this->request );
			}
		} catch ( \Exception $e ) {
			// Don't throw authentication exceptions in debug mode so challenge
			// headers can be sent to display login prompts.
			// But throw them when running PHPUnit tests.
			if ( is_running_unit_tests() || ( is_debug_mode() && ! $e instanceof AuthenticationException ) ) {
				throw $e;
			}

			$response = Response::from_exception( $e );
		}

		$this->send_headers( $response->get_headers() );
		status_header( $response->get_status() );
		$response->get_body()->emit();
		exit;
	}

	/**
	 * Check authentication.
	 *
	 * Calls the WP_REST_Server authentication method to leverage authentication
	 * handlers built for the REST API.
	 *
	 * @since 0.1.0
	 *
	 * @see WP_REST_Server::check_authentication()
	 *
	 * @return null|Response
	 */
	protected function check_authentication(): ?Response {
		$server = new WP_REST_Server();
		$result = $server->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			return null;
		}

		return Response::from_rest_authentication_error( $result );
	}

	/**
	 * Send an HTTP header.
	 *
	 * @since 0.1.0
	 *
	 * @see WP_REST_Server::send_header()
	 *
	 * @param string      $name  Header name.
	 * @param string|bool $value Header value.
	 */
	protected function send_header( string $name, $value ) {
		/*
		 * Sanitize as per RFC2616 (Section 4.2):
		 *
		 * Any LWS that occurs between field-content MAY be replaced with a
		 * single SP before interpreting the field value or forwarding the
		 * message downstream.
		 */
		$value = preg_replace( '/\s+/', ' ', $value );
		header( sprintf( '%s: %s', $name, $value ) );
	}

	/**
	 * Send HTTP headers.
	 *
	 * @since 0.1.0
	 *
	 * @param array $headers HTTP headers.
	 */
	protected function send_headers( array $headers ) {
		foreach ( $headers as $name => $value ) {
			$this->send_header( $name, $value );
		}
	}

	/**
	 * Retrieve the controller for a route.
	 *
	 * @since 0.1.0
	 *
	 * @param string $route Route identifier.
	 * @return Route
	 */
	protected function get_route_controller( string $route ): Route {
		return $this->controllers->get( $route );
	}
}
