<?php
/**
 * Upgrade routines.
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
use Psr\Log\LoggerInterface;
use Pressody\Conductor\Capabilities as Caps;

use const Pressody\Conductor\VERSION;

/**
 * Class for upgrade routines.
 *
 * @since 0.1.0
 */
class Upgrade extends AbstractHookProvider {
	/**
	 * Version option name.
	 *
	 * @var string
	 */
	const VERSION_OPTION_NAME = 'pressody_conductor_version';

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param LoggerInterface   $logger     Logger.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
	}

	/**
	 * Upgrade when the database version is outdated.
	 *
	 * @since 0.1.0
	 */
	public function maybe_upgrade() {
		$saved_version = get_option( self::VERSION_OPTION_NAME, '0' );

		if ( version_compare( $saved_version, '1.0.0', '<' ) ) {
			Caps::register();
		}

		if ( version_compare( $saved_version, VERSION, '<' ) ) {
			if ( update_option( self::VERSION_OPTION_NAME, VERSION ) ) {
				$this->logger->notice( 'Upgraded from version "{previous_version}" to "{new_version}".',
					[
						'previous_version'    => $saved_version,
						'new_version' => VERSION,
					]
				);
			}
		}
	}
}
