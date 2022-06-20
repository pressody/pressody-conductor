<?php
/**
 * Client to communicate with an external Composer repository.
 *
 * Borrowed from WP-CLI https://github.com/wp-cli/wp-cli/blob/master/php/WP_CLI/PackageManagerEventSubscriber.php
 *
 * @since   0.8.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Conductor\Composer;

use Composer\DependencyResolver\Rule;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\BaseIO;

/**
 * A Composer Event subscriber so we can keep track of what's happening inside Composer
 */
class ComposerPackageManagerEventSubscriber implements EventSubscriberInterface {

	/**
	 * @since 0.8.0
	 *
	 * @var BaseIO|null
	 */
	static protected ?BaseIO $io = null;

	/**
	 * @since 0.8.0
	 *
	 * @param BaseIO|null $io
	 */
	public function __construct( BaseIO $io ) {
		self::$io = $io;
	}

	/**
	 * @since 0.8.0
	 *
	 * @return string[]
	 */
	public static function getSubscribedEvents(): array {

		return [
			PackageEvents::PRE_PACKAGE_INSTALL  => 'pre_install',
			PackageEvents::POST_PACKAGE_INSTALL => 'post_install',
		];
	}

	/**
	 * @since 0.8.0
	 *
	 * @param PackageEvent $event
	 */
	public static function pre_install( PackageEvent $event ) {
		$operation_message = $event->getOperation()->__toString();
		$event->getIO()->info( ' - ' . $operation_message );
	}

	/**
	 * @since 0.8.0
	 *
	 * @param PackageEvent $event
	 */
	public static function post_install( PackageEvent $event ) {

		$operation = $event->getOperation();

		// getReason() was removed in Composer v2 without replacement.
		if ( ! method_exists( $operation, 'getReason' ) ) {
			return;
		}

		$reason = $operation->getReason();
		if ( $reason instanceof Rule ) {

			switch ( $reason->getReason() ) {

				case Rule::RULE_PACKAGE_CONFLICT:
				case Rule::RULE_PACKAGE_SAME_NAME:
				case Rule::RULE_PACKAGE_REQUIRES:
					$composer_error = $reason->getPrettyString( $event->getPool() );
					break;

			}

			if ( ! empty( $composer_error ) ) {
				self::$io->info( sprintf( ' - Warning: %s', $composer_error ) );
			}
		}

	}

}
