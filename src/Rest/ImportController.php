<?php
/**
 * REST controller for importing scan results.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Rest;

use Soderlind\Kjeks\Scan\ScanImporter;
use Soderlind\Kjeks\Scan\ScanKeyAuth;
use Soderlind\Kjeks\Scan\ScanValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Accepts scan payloads from CI (for example, a scheduled GitHub Action).
 *
 * Authentication accepts either the shared scanner key (see {@see ScanKeyAuth},
 * sent in the `X-Kjeks-Key` header or `scan_key` query argument) or the standard
 * WordPress REST stack with a `manage_network` capability. Imported
 * observations remain unreviewed until an administrator approves them.
 */
final class ImportController {

	public const NAMESPACE = 'kjeks/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_import' ),
				'args'                => array(
					'blog_id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'observations' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
	}

	public function can_import( WP_REST_Request $request ): bool {
		if ( ScanKeyAuth::is_authorized( $request ) ) {
			return true;
		}

		return is_multisite()
			? current_user_can( 'manage_network' )
			: current_user_can( 'manage_options' );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$payload = array(
			'blog_id'      => (int) $request->get_param( 'blog_id' ),
			'observations' => (array) $request->get_param( 'observations' ),
		);

		$result = ( new ScanValidator() )->validate( $payload );

		if ( ! $result['valid'] ) {
			return new WP_Error(
				'kjeks_invalid_scan',
				__( 'The scan payload could not be validated.', 'kjeks' ),
				array(
					'status' => 400,
					'errors' => $result['errors'],
				)
			);
		}

		$blog_id = $result['blog_id'];

		if ( is_multisite() && ! get_blog_details( $blog_id ) ) {
			return new WP_Error( 'kjeks_unknown_site', __( 'Unknown site.', 'kjeks' ), array( 'status' => 404 ) );
		}

		$imported = ( new ScanImporter() )->import( $blog_id, $result['trackers'] );

		return new WP_REST_Response(
			array(
				'imported' => $imported,
				'reviewed' => false,
				'warnings' => $result['warnings'],
				'blog_id'  => $blog_id,
			),
			201
		);
	}
}
