<?php
/**
 * Tracker value object.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

use Soderlind\Kjeks\Consent\Categories;

/**
 * A single tracker definition or observation.
 *
 * Immutable. Use `with_review()` / `with_last_observed()` to derive updated
 * copies. A tracker is "reviewed" only after an administrator classifies it;
 * discoveries are never auto-classified as necessary.
 */
final class Tracker {

	public const PARTY_FIRST = 'first';
	public const PARTY_THIRD = 'third';

	/**
	 * @param list<int> $sites Blog ids where this tracker was observed. Empty means network-wide.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $category,
		public readonly bool $reviewed = false,
		public readonly string $provider = '',
		public readonly string $purpose = '',
		public readonly string $party = self::PARTY_THIRD,
		public readonly string $storage_type = 'cookie',
		public readonly string $domain = '',
		public readonly string $path = '/',
		public readonly string $retention = '',
		public readonly string $source = '',
		public readonly string $documentation_url = '',
		public readonly int $first_observed = 0,
		public readonly int $last_observed = 0,
		public readonly array $sites = array(),
	) {}

	/**
	 * Builds a tracker from a (possibly untrusted) associative array.
	 *
	 * Category falls back to marketing — never necessary — for unknown or
	 * missing values, so nothing is silently treated as essential.
	 *
	 * @param array<string, mixed> $data Raw data.
	 */
	public static function from_array( array $data ): self {
		$category = isset( $data['category'] ) ? (string) $data['category'] : Categories::MARKETING;
		if ( ! Categories::exists( $category ) ) {
			$category = Categories::MARKETING;
		}

		$party = ( isset( $data['party'] ) && self::PARTY_FIRST === $data['party'] ) ? self::PARTY_FIRST : self::PARTY_THIRD;

		return new self(
			id: self::slug( (string) ( $data['id'] ?? ( $data['name'] ?? '' ) ) ),
			name: (string) ( $data['name'] ?? '' ),
			category: $category,
			reviewed: ! empty( $data['reviewed'] ),
			provider: (string) ( $data['provider'] ?? '' ),
			purpose: (string) ( $data['purpose'] ?? '' ),
			party: $party,
			storage_type: (string) ( $data['storage_type'] ?? 'cookie' ),
			domain: (string) ( $data['domain'] ?? '' ),
			path: (string) ( $data['path'] ?? '/' ),
			retention: (string) ( $data['retention'] ?? '' ),
			source: (string) ( $data['source'] ?? '' ),
			documentation_url: esc_url_raw( (string) ( $data['documentation_url'] ?? '' ) ),
			first_observed: (int) ( $data['first_observed'] ?? 0 ),
			last_observed: (int) ( $data['last_observed'] ?? 0 ),
			sites: array_values( array_unique( array_map( 'intval', (array) ( $data['sites'] ?? array() ) ) ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                => $this->id,
			'name'              => $this->name,
			'category'          => $this->category,
			'reviewed'          => $this->reviewed,
			'provider'          => $this->provider,
			'purpose'           => $this->purpose,
			'party'             => $this->party,
			'storage_type'      => $this->storage_type,
			'domain'            => $this->domain,
			'path'              => $this->path,
			'retention'         => $this->retention,
			'source'            => $this->source,
			'documentation_url' => $this->documentation_url,
			'first_observed'    => $this->first_observed,
			'last_observed'     => $this->last_observed,
			'sites'             => $this->sites,
		);
	}

	/**
	 * Returns a reviewed copy classified into the given category.
	 */
	public function with_review( string $category ): self {
		$data             = $this->to_array();
		$data['category'] = Categories::exists( $category ) ? $category : $this->category;
		$data['reviewed'] = true;

		return self::from_array( $data );
	}

	/**
	 * Returns a copy with an updated last-observed timestamp.
	 */
	public function with_last_observed( int $time ): self {
		$data                  = $this->to_array();
		$data['last_observed'] = $time;

		return self::from_array( $data );
	}

	/**
	 * Returns a copy recording an occurrence on a site (aggregation).
	 */
	public function with_site( int $blog_id, int $time = 0 ): self {
		$data          = $this->to_array();
		$data['sites'] = array_values( array_unique( array_merge( $this->sites, array( $blog_id ) ) ) );
		if ( $time > 0 ) {
			$data['last_observed'] = max( $this->last_observed, $time );
			if ( 0 === $this->first_observed ) {
				$data['first_observed'] = $time;
			}
		}

		return self::from_array( $data );
	}

	/**
	 * Normalises an identifier into a stable slug.
	 */
	public static function slug( string $value ): string {
		$slug = sanitize_key( str_replace( array( ' ', '.', '/' ), '-', $value ) );

		return '' === $slug ? substr( md5( $value ), 0, 12 ) : $slug;
	}
}
