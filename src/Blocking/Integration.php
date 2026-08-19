<?php
/**
 * Integration value object.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Blocking;

use Soderlind\Kjeks\Consent\Categories;

/**
 * A third-party technology registered against a consent category.
 *
 * An integration declares the WordPress script handles it owns, any inert
 * `src` scripts and inline snippets to gate, and optional placeholder markup.
 * Activation and cleanup happen client-side when consent changes.
 */
final class Integration {

	/**
	 * @param list<string>                                           $handles      Registered wp_enqueue_script handles to gate.
	 * @param list<array{src: string, attrs: array<string, string>}> $src_scripts  External scripts to emit inert.
	 * @param list<string>                                           $inline       Inline JS snippets to emit inert.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $category,
		public readonly string $label = '',
		public readonly array $handles = array(),
		public readonly array $src_scripts = array(),
		public readonly array $inline = array(),
	) {}

	/**
	 * @param array<string, mixed> $args Registration arguments.
	 */
	public static function from_args( string $id, array $args ): self {
		$category = isset( $args['category'] ) ? (string) $args['category'] : Categories::MARKETING;
		if ( ! Categories::exists( $category ) || Categories::is_required( $category ) ) {
			// Never gate against necessary; fall back to marketing.
			$category = Categories::exists( $category ) && ! Categories::is_required( $category ) ? $category : Categories::MARKETING;
		}

		return new self(
			id: sanitize_key( $id ),
			category: $category,
			label: isset( $args['label'] ) ? (string) $args['label'] : $id,
			handles: array_values( array_map( 'strval', (array) ( $args['handles'] ?? array() ) ) ),
			src_scripts: self::normalise_src( (array) ( $args['src_scripts'] ?? array() ) ),
			inline: array_values( array_map( 'strval', (array) ( $args['inline'] ?? array() ) ) ),
		);
	}

	/**
	 * @param array<int|string, mixed> $items Raw src script definitions.
	 * @return list<array{src: string, attrs: array<string, string>}>
	 */
	private static function normalise_src( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$out[] = array(
					'src'   => $item,
					'attrs' => array(),
				);
				continue;
			}

			if ( is_array( $item ) && isset( $item['src'] ) ) {
				$attrs = array();
				foreach ( (array) ( $item['attrs'] ?? array() ) as $key => $value ) {
					$attrs[ (string) $key ] = (string) $value;
				}

				$out[] = array(
					'src'   => (string) $item['src'],
					'attrs' => $attrs,
				);
			}
		}

		return $out;
	}
}
