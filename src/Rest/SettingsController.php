<?php
/**
 * REST controller for per-site configuration.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Rest;

use Soderlind\Kjeks\Consent\Categories;
use Soderlind\Kjeks\Consent\PolicyVersion;
use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Inventory\SiteStore;
use Soderlind\Kjeks\Inventory\Tracker;
use Soderlind\Kjeks\Inventory\TrackerRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes the current site's Kjeks configuration to the admin React app.
 *
 * All routes require `manage_options` and the standard REST nonce.
 */
final class SettingsController {

	public const NAMESPACE = 'kjeks/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site-config',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_config' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_config(): WP_REST_Response {
		$site      = new SiteStore( get_current_blog_id() );
		$network   = new NetworkStore();
		$inventory = new InventoryResolver( new TrackerRegistry(), $site );

		$trackers = array();

		// Network trackers are read-only here; the network admin owns them.
		foreach ( $inventory->scoped_network_trackers() as $tracker ) {
			$data            = $tracker->to_array();
			$data['source']  = 'network';
			$data['locked']  = true;
			$data['enabled'] = true;
			$trackers[]      = $data;
		}

		// Site-local trackers remain editable by the site admin.
		foreach ( $site->local_trackers() as $tracker ) {
			$data            = $tracker->to_array();
			$data['source']  = 'local';
			$data['locked']  = false;
			$data['enabled'] = true;
			$trackers[]      = $data;
		}

		return new WP_REST_Response(
			array(
				'categories'      => array_map(
					static fn ( string $slug, array $meta ): array => array(
						'slug'     => $slug,
						'label'    => $meta['label'],
						'required' => $meta['required'],
					),
					array_keys( Categories::all() ),
					array_values( Categories::all() )
				),
				'trackers'        => $trackers,
				'content'         => $site->content_overrides(),
				'networkContent'  => $network->content(),
				'policyVersion'   => PolicyVersion::current(),
				'reviewedCount'   => count( $inventory->reviewed() ),
				'unreviewedCount' => count( array_filter( $inventory->all(), static fn ( Tracker $t ): bool => ! $t->reviewed ) ),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_config( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$site = new SiteStore( get_current_blog_id() );

		// Only site-local trackers are editable here; network trackers are read-only.
		$local = array();
		foreach ( (array) $request->get_param( 'localTrackers' ) as $item ) {
			if ( is_array( $item ) ) {
				$tracker               = Tracker::from_array( $item );
				$local[ $tracker->id ] = $tracker;
			}
		}
		$site->save_local_trackers( $local );

		$content = array();
		foreach ( array( 'heading', 'body', 'privacy_url', 'accent' ) as $field ) {
			$value = $request->get_param( "content_{$field}" );
			if ( null === $value ) {
				$raw   = (array) $request->get_param( 'content' );
				$value = $raw[ $field ] ?? '';
			}
			$content[ $field ] = 'privacy_url' === $field
				? esc_url_raw( (string) $value )
				: sanitize_text_field( (string) $value );
		}
		$site->save_content_overrides( $content );

		$policy = (int) $request->get_param( 'policyVersion' );
		if ( $policy > 0 ) {
			PolicyVersion::set( $policy );
		}

		InventoryResolver::flush_cache();

		return $this->get_config();
	}
}
