<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Frontend\Stape;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Ask Stape's API for the loader it issues for a server-side container.
 *
 * Called only when an administrator saves settings or asks for a refresh, never
 * on a schedule or on a page view.
 */
final class StapeLoaderClient {

	/**
	 * The endpoint of the global region.
	 *
	 * @var string
	 */
	const ENDPOINT_GLOBAL = 'https://api.app.stape.io/api/v2/container/%s/custom-loader';

	/**
	 * The endpoint of the EU region, asked when the global region does not know the container.
	 *
	 * @var string
	 */
	const ENDPOINT_EU = 'https://api.app.eu.stape.io/api/v2/container/%s/custom-loader';

	/**
	 * Seconds to wait for each request.
	 *
	 * Stape's own plugin waits 20 seconds, but it asks from a shutdown hook
	 * where nobody is waiting. Here an administrator is watching a save, and an
	 * unanswered request only leaves the standard loader in place, so a shorter
	 * wait is the better trade.
	 *
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * The request did not complete.
	 *
	 * @var string
	 */
	const REASON_NETWORK = 'network';

	/**
	 * The response was not JSON.
	 *
	 * @var string
	 */
	const REASON_INVALID_JSON = 'invalid_json';

	/**
	 * The response carried no loader.
	 *
	 * @var string
	 */
	const REASON_NO_LOADER = 'no_loader';

	/**
	 * The loader could not be read safely.
	 *
	 * @var string
	 */
	const REASON_UNPARSEABLE = 'unparseable';

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * The request body Stape expects.
	 *
	 * The container ID goes as stored, with its `GTM-` prefix, which Stape
	 * requires. The configured data layer name goes too, because Stape encodes
	 * it into the loader address.
	 *
	 * @param array{identifier: string, gtm_id: string, domain: string, cookie_keeper: bool, datalayer_name: string} $inputs The settings the loader is issued for.
	 *
	 * @return array<string, string>
	 */
	public static function request_body( array $inputs ): array {
		$body = [
			'webGtmId'            => $inputs['gtm_id'],
			'domain'              => $inputs['domain'],
			'source'              => 'wordpress',
			'dataLayerObjectName' => $inputs['datalayer_name'],
		];

		if ( $inputs['cookie_keeper'] ) {
			$body['userIdentifierType']  = 'cookie';
			$body['userIdentifierValue'] = Stape::COOKIE_KEEPER_NAME;
		}

		return $body;
	}

	/**
	 * Fetch and parse the loader.
	 *
	 * @param array{identifier: string, gtm_id: string, domain: string, cookie_keeper: bool, datalayer_name: string} $inputs The settings the loader is issued for.
	 *
	 * @return array{loader: array{path: string, param: string, value: string}|null, reason: string, region: string}
	 */
	public function fetch( array $inputs ): array {
		$body   = (string) wp_json_encode( self::request_body( $inputs ) );
		$region = 'global';

		$response = $this->post( sprintf( self::ENDPOINT_GLOBAL, rawurlencode( $inputs['identifier'] ) ), $body );

		if ( ! is_wp_error( $response ) && 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$region   = 'eu';
			$response = $this->post( sprintf( self::ENDPOINT_EU, rawurlencode( $inputs['identifier'] ) ), $body );
		}

		if ( is_wp_error( $response ) ) {
			return $this->failure( self::REASON_NETWORK, 0, $region );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 ) {
			return $this->failure( 'http_' . $status, $status, $region );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return $this->failure( self::REASON_INVALID_JSON, $status, $region );
		}

		$code = $decoded['body']['jsCode'] ?? null;

		if ( ! is_string( $code ) || $code === '' ) {
			return $this->failure( self::REASON_NO_LOADER, $status, $region );
		}

		$loader = StapeLoader::parse( $code, $inputs['domain'] );

		if ( null === $loader ) {
			return $this->failure( self::REASON_UNPARSEABLE, $status, $region );
		}

		$this->log( $status, 'ok', $region );

		return [
			'loader' => $loader,
			'reason' => '',
			'region' => $region,
		];
	}

	/**
	 * Send one request.
	 *
	 * @param string $url  The endpoint.
	 * @param string $body The JSON request body.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private function post( string $url, string $body ) {
		return wp_remote_post(
			$url,
			[
				'headers' => [
					'accept'       => 'application/json',
					'content-type' => 'application/json',
				],
				'body'    => $body,
				'timeout' => self::TIMEOUT,
			]
		);
	}

	/**
	 * Report a failed fetch.
	 *
	 * @param string $reason Why it failed.
	 * @param int    $status The HTTP status, or 0.
	 * @param string $region The region asked last.
	 *
	 * @return array{loader: null, reason: string, region: string}
	 */
	private function failure( string $reason, int $status, string $region ): array {
		$this->log( $status, $reason, $region );

		return [
			'loader' => null,
			'reason' => $reason,
			'region' => $region,
		];
	}

	/**
	 * Record the outcome in the debug log, when it is switched on.
	 *
	 * Only the status and the reason are written. Neither body is, since the
	 * request carries the container ID and the response carries the loader.
	 *
	 * @param int    $status The HTTP status, or 0.
	 * @param string $reason The outcome.
	 * @param string $region The region asked last.
	 *
	 * @return void
	 */
	private function log( int $status, string $reason, string $region ): void {
		if ( ! $this->options->get( 'general', 'debug_log' ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,QITStandard.PHP.DebugCode.DebugFunctionFound -- Written only when the site owner has switched the debug log on.
		error_log( sprintf( '[GTM Kit] Stape loader request (%s region): HTTP %d, %s', $region, $status, $reason ) );
	}
}
