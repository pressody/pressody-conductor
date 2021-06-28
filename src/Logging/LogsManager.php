<?php
/**
 * Logs management routines.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Conductor\Logging;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Psr\Log\LoggerInterface;

/**
 * Class to manage logs.
 *
 * @since 0.1.0
 */
class LogsManager extends AbstractHookProvider {

	/**
	 * Logger.
	 *
	 * @since 0.1.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param LoggerInterface   $logger          Logger.
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
		$this->add_action( 'pixelgradelt_conductor/cleanup_logs', 'cleanup_logs' );
	}

	protected function cleanup_logs() {
		if ( is_callable( array( $this->logger, 'clear_expired_logs' ) ) ) {
			$this->logger->clear_expired_logs();
		}
	}
}
