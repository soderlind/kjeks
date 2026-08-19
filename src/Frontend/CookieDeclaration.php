<?php
/**
 * Cookie declaration renderer.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Frontend;

use Soderlind\Kjeks\Consent\Categories;
use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\Tracker;

/**
 * Renders an accessible cookie declaration from the reviewed inventory.
 */
final class CookieDeclaration {

	public function __construct( private readonly InventoryResolver $inventory ) {}

	public function render(): string {
		$grouped = $this->inventory->reviewed_by_category();
		$labels  = Categories::all();

		if ( array() === $grouped ) {
			return '<p class="kjeks-declaration__empty">' . esc_html__( 'No trackers have been documented yet.', 'kjeks' ) . '</p>';
		}

		$html = '<div class="kjeks-declaration">';

		foreach ( $labels as $slug => $meta ) {
			$trackers = $grouped[ $slug ] ?? array();
			if ( array() === $trackers ) {
				continue;
			}

			$html .= '<section class="kjeks-declaration__category">';
			$html .= '<h3>' . esc_html( $meta['label'] ) . '</h3>';
			$html .= '<p>' . esc_html( $meta['description'] ) . '</p>';
			$html .= '<table class="kjeks-declaration__table"><thead><tr>'
				. '<th scope="col">' . esc_html__( 'Name', 'kjeks' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Provider', 'kjeks' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Purpose', 'kjeks' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Type', 'kjeks' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Party', 'kjeks' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Retention', 'kjeks' ) . '</th>'
				. '</tr></thead><tbody>';

			foreach ( $trackers as $tracker ) {
				$party = Tracker::PARTY_FIRST === $tracker->party
					? esc_html__( 'First-party', 'kjeks' )
					: esc_html__( 'Third-party', 'kjeks' );

				$html .= '<tr>'
					. '<td>' . esc_html( $tracker->name ) . '</td>'
					. '<td>' . esc_html( $tracker->provider ) . '</td>'
					. '<td>' . esc_html( $tracker->purpose ) . '</td>'
					. '<td>' . esc_html( $tracker->storage_type ) . '</td>'
					. '<td>' . $party . '</td>'
					. '<td>' . esc_html( $tracker->retention ) . '</td>'
					. '</tr>';
			}

			$html .= '</tbody></table></section>';
		}

		$html .= '</div>';

		return $html;
	}
}
