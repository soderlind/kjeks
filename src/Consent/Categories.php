<?php
/**
 * Consent categories.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Consent;

/**
 * Defines the consent categories and their properties.
 *
 * The four canonical categories are always present. Other plugins may add
 * optional categories through the `kjeks_categories` filter; `necessary`
 * can never be removed or made optional.
 */
final class Categories {

	public const NECESSARY   = 'necessary';
	public const PREFERENCES = 'preferences';
	public const ANALYTICS   = 'analytics';
	public const MARKETING   = 'marketing';

	/**
	 * Canonical, non-removable categories.
	 *
	 * @return array<string, array{label: string, description: string, required: bool}>
	 */
	public static function defaults(): array {
		return array(
			self::NECESSARY   => array(
				'label'       => __( 'Necessary', 'kjeks' ),
				'description' => __( 'Required for the site to function. Always active.', 'kjeks' ),
				'required'    => true,
			),
			self::PREFERENCES => array(
				'label'       => __( 'Preferences', 'kjeks' ),
				'description' => __( 'Remembers choices such as language or region.', 'kjeks' ),
				'required'    => false,
			),
			self::ANALYTICS   => array(
				'label'       => __( 'Analytics', 'kjeks' ),
				'description' => __( 'Helps understand how visitors use the site.', 'kjeks' ),
				'required'    => false,
			),
			self::MARKETING   => array(
				'label'       => __( 'Marketing', 'kjeks' ),
				'description' => __( 'Used to deliver and measure advertising.', 'kjeks' ),
				'required'    => false,
			),
		);
	}

	/**
	 * All categories, including any added via filter.
	 *
	 * The `necessary` category is always present and always required.
	 *
	 * @return array<string, array{label: string, description: string, required: bool}>
	 */
	public static function all(): array {
		$categories = self::defaults();

		/**
		 * Filters the available consent categories.
		 *
		 * Added categories default to optional (disabled). The `necessary`
		 * category is re-asserted after filtering and cannot be removed or
		 * made optional.
		 *
		 * @param array<string, array{label: string, description: string, required: bool}> $categories Categories keyed by slug.
		 */
		$categories = (array) apply_filters( 'kjeks_categories', $categories );

		$categories[ self::NECESSARY ]             = self::defaults()[ self::NECESSARY ];
		$categories[ self::NECESSARY ]['required'] = true;

		foreach ( $categories as $slug => $data ) {
			if ( self::NECESSARY !== $slug ) {
				$categories[ $slug ]['required'] = false;
			}
		}

		return $categories;
	}

	/**
	 * Category slugs.
	 *
	 * @return list<string>
	 */
	public static function slugs(): array {
		return array_keys( self::all() );
	}

	/**
	 * Optional (non-necessary) category slugs.
	 *
	 * @return list<string>
	 */
	public static function optional(): array {
		return array_values( array_filter( self::slugs(), static fn ( string $slug ): bool => self::NECESSARY !== $slug ) );
	}

	/**
	 * Whether a slug is a known category.
	 */
	public static function exists( string $slug ): bool {
		return array_key_exists( $slug, self::all() );
	}

	/**
	 * Whether a category is required (cannot be disabled).
	 */
	public static function is_required( string $slug ): bool {
		$all = self::all();

		return isset( $all[ $slug ] ) && true === $all[ $slug ]['required'];
	}
}
