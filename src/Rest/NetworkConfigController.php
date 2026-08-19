<?php
/**
 * REST controller for network-wide tracker review.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Rest;

use Soderlind\Kjeks\Consent\Categories;
use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Inventory\Tracker;
use Soderlind\Kjeks\Inventory\TrackerRegistry;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Powers the network admin review screen.
 *
 * Exposes the aggregated tracker registry so a network admin can classify each
 * cookie once (with bulk actions) instead of every site reviewing duplicates.
 * Requires `manage_network`.
 */
final class NetworkConfigController {

	public const NAMESPACE = 'kjeks/v1';

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/network-config',
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
		return is_multisite() ? current_user_can( 'manage_network' ) : current_user_can( 'manage_options' );
	}

	public function get_config(): WP_REST_Response {
		$network  = new NetworkStore();
		$trackers = ( new TrackerRegistry() )->trackers();

		$rows      = array();
		$reviewed  = 0;
		$pending   = 0;
		$site_name = array();

		foreach ( $trackers as $tracker ) {
			$data                = $tracker->to_array();
			$data['sites_count'] = count( $tracker->sites );
			$rows[]              = $data;

			if ( $tracker->reviewed ) {
				++$reviewed;
			} else {
				++$pending;
			}

			foreach ( $tracker->sites as $blog_id ) {
				if ( ! isset( $site_name[ $blog_id ] ) ) {
					$site_name[ $blog_id ] = $this->site_label( (int) $blog_id );
				}
			}
		}

		return new WP_REST_Response(
			array(
				'categories'           => array_map(
					static fn ( string $slug, array $meta ): array => array(
						'slug'     => $slug,
						'label'    => $meta['label'],
						'required' => $meta['required'],
					),
					array_keys( Categories::all() ),
					array_values( Categories::all() )
				),
				'trackers'             => array_values( $rows ),
				'siteNames'            => $site_name,
				'content'              => $network->content(),
				'deleteOnUninstall'    => $network->delete_on_uninstall(),
				'bannerDefaultVisible' => $network->banner_default_visible(),
				'reviewedCount'        => $reviewed,
				'pendingCount'         => $pending,
			)
		);
	}

	public function save_config( WP_REST_Request $request ): WP_REST_Response {
		$registry = new TrackerRegistry();
		$network  = new NetworkStore();
		$trackers = $registry->trackers();

		// Per-tracker reviews (also used for bulk apply on the client).
		foreach ( (array) $request->get_param( 'reviews' ) as $id => $review ) {
			$id = Tracker::slug( (string) $id );
			if ( ! isset( $trackers[ $id ] ) || ! is_array( $review ) ) {
				continue;
			}

			$data = $trackers[ $id ]->to_array();
			if ( isset( $review['category'] ) && Categories::exists( (string) $review['category'] ) ) {
				$data['category'] = (string) $review['category'];
			}
			if ( isset( $review['reviewed'] ) ) {
				$data['reviewed'] = (bool) $review['reviewed'];
			}
			$trackers[ $id ] = Tracker::from_array( $data );
		}

		// Removals.
		foreach ( (array) $request->get_param( 'remove' ) as $id ) {
			unset( $trackers[ Tracker::slug( (string) $id ) ] );
		}

		// Manual addition (network-wide: empty site list).
		$add = $request->get_param( 'add' );
		if ( is_array( $add ) && isset( $add['name'] ) && '' !== trim( (string) $add['name'] ) ) {
			$tracker                  = Tracker::from_array(
				array(
					'name'     => sanitize_text_field( (string) $add['name'] ),
					'provider' => sanitize_text_field( (string) ( $add['provider'] ?? '' ) ),
					'category' => (string) ( $add['category'] ?? Categories::MARKETING ),
					'reviewed' => false,
					'source'   => 'manual',
				)
			);
			$trackers[ $tracker->id ] = $tracker;
		}

		$registry->save_trackers( $trackers );

		$content = $request->get_param( 'content' );
		if ( is_array( $content ) ) {
			$network->save_content(
				array(
					'heading'     => sanitize_text_field( (string) ( $content['heading'] ?? '' ) ),
					'body'        => sanitize_textarea_field( (string) ( $content['body'] ?? '' ) ),
					'privacy_url' => esc_url_raw( (string) ( $content['privacy_url'] ?? '' ) ),
					'accent'      => sanitize_text_field( (string) ( $content['accent'] ?? '' ) ),
				)
			);
		}

		if ( null !== $request->get_param( 'deleteOnUninstall' ) ) {
			$network->set_delete_on_uninstall( (bool) $request->get_param( 'deleteOnUninstall' ) );
		}

		if ( null !== $request->get_param( 'bannerDefaultVisible' ) ) {
			$network->set_banner_default_visible( (bool) $request->get_param( 'bannerDefaultVisible' ) );
		}

		InventoryResolver::flush_cache();

		return $this->get_config();
	}

	private function site_label( int $blog_id ): string {
		if ( ! is_multisite() ) {
			return get_bloginfo( 'name' );
		}

		$details = get_blog_details( $blog_id );

		return $details ? $details->blogname : ( '#' . $blog_id );
	}
}
