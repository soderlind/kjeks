<?php
/**
 * REST controller for the scanner configuration.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Rest;

use Soderlind\Kjeks\Scan\ScanConfig;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Serves the scanner site list so CI (for example, a GitHub Action) can fetch
 * it dynamically instead of committing a config file.
 *
 * Authentication is the standard WordPress REST stack — an application password
 * is the intended CI credential — and the caller must hold `manage_network`.
 * The response enumerates every public subsite, so the endpoint is never open.
 */
final class ScanConfigController {

	public const NAMESPACE = 'kjeks/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/scan-config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => array( $this, 'can_read' ),
				'args'                => array(
					'include' => array(
						'type'        => 'string',
						'required'    => false,
						'description' => 'Comma-separated blog ids to include.',
					),
					'paths'   => array(
						'type'        => 'string',
						'required'    => false,
						'description' => 'Comma-separated representative paths.',
					),
				),
			)
		);
	}

	public function can_read(): bool {
		return is_multisite()
			? current_user_can( 'manage_network' )
			: current_user_can( 'manage_options' );
	}

	public function get_config( WP_REST_Request $request ): WP_REST_Response {
		$include = $this->csv_ints( (string) $request->get_param( 'include' ) );
		$paths   = $this->csv_strings( (string) $request->get_param( 'paths' ) );

		$config = ( new ScanConfig() )->build( $include, array() === $paths ? array( '/' ) : $paths );

		return new WP_REST_Response( $config );
	}

	/**
	 * @return array<int, int>
	 */
	private function csv_ints( string $value ): array {
		if ( '' === $value ) {
			return array();
		}

		return array_values( array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $value ) ) ) ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function csv_strings( string $value ): array {
		if ( '' === $value ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
