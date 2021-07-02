<?php
/**
 * Composition management routines.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Conductor\Composition;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Composer\Json\JsonFile;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\ParsingException;
use function PixelgradeLT\Conductor\is_debug_mode;
use function PixelgradeLT\Conductor\is_dev_url;
use WP_Http as HTTP;

/**
 * Class to manage the site composition.
 *
 * @since 0.1.0
 */
class CompositionManager extends AbstractHookProvider {

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
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_action( 'pixelgradelt_conductor/check_update', 'check_update' );
//		$this->add_action( 'after_setup_theme', 'check_update' );
	}

	/**
	 * Check with LT Records if the current site composition should be updated.
	 */
	protected function check_update() {
		if ( ! defined( 'LT_RECORDS_API_KEY' ) || empty( LT_RECORDS_API_KEY )
		     || ! defined( 'LT_RECORDS_API_PWD' ) || empty( LT_RECORDS_API_PWD )
		     || ! defined( 'LT_RECORDS_COMPOSITION_REFRESH_URL' ) || empty( LT_RECORDS_COMPOSITION_REFRESH_URL )
		) {
			$this->logger->warning( 'Could not check for composition update because there are missing or empty environment variables.' );

			return false;
		}

		// Read the current contents of the site's composer.json (the composition).
		$composerJsonFile = new JsonFile( \path_join( LT_ROOT_DIR, 'composer.json' ) );
		if ( ! $composerJsonFile->exists() ) {
			$this->logger->error( 'The site\'s composer.json file doesn\'t exist.' );

			return false;
		}
		try {
			$composerJsonCurrentContents = $composerJsonFile->read();
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be read: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		} catch ( ParsingException $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be parsed: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		}

		$request_args = [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( LT_RECORDS_API_KEY . ':' . LT_RECORDS_API_PWD ),
			],
			'timeout'   => 5,
			'sslverify' => ! ( is_debug_mode() || is_dev_url( LT_RECORDS_COMPOSITION_REFRESH_URL ) ),
			// Do the json_encode ourselves so it maintains types. Note the added Content-Type header also.
			'body'      => json_encode( [
				'composer' => $composerJsonCurrentContents,
			] ),
		];

		$response = wp_remote_post( LT_RECORDS_COMPOSITION_REFRESH_URL, $request_args );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= HTTP::BAD_REQUEST ) {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$accepted_keys = array_fill_keys( [ 'code', 'message', 'data' ], '' );
			$body          = array_replace( $accepted_keys, array_intersect_key( $body, $accepted_keys ) );
			$this->logger->error( 'The composition update check failed with code "{code}": {message}',
				[
					'code'    => $body['code'],
					'message' => $body['message'],
					'data'    => $body['data'],
				]
			);

			return false;
		}

		// If we have nothing to update, bail.
		if ( wp_remote_retrieve_response_code( $response ) === HTTP::NO_CONTENT ) {
			return false;
		}

		// We get back the entire composer.json contents.
		$receivedComposerJson = json_decode( wp_remote_retrieve_body( $response ), true );

		// Now we need to prepare the new contents and write them (if needed) the same way Composer does it.
		try {
			$composerJsonFile->write( $receivedComposerJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		} catch ( \Exception $e ) {
			$this->logger->error( 'The site\'s composer.json file could not be written with the updated contents: {message}',
				[
					'message'   => $e->getMessage(),
					'exception' => $e,
				]
			);

			return false;
		}

		return true;
	}
}
