<?php
/**
 * Integration registry.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Blocking;

/**
 * Central registry of consent integrations.
 *
 * Integrations are registered on the `kjeks_register_integrations` action (or
 * any time before `wp_head`) via `kjeks_register_integration()`.
 */
final class IntegrationRegistry {

	/**
	 * @var array<string, Integration>
	 */
	private static array $integrations = array();

	/**
	 * Registers (or replaces) an integration.
	 *
	 * @param array<string, mixed> $args Registration arguments.
	 */
	public static function register( string $id, array $args ): Integration {
		$integration                            = Integration::from_args( $id, $args );
		self::$integrations[ $integration->id ] = $integration;

		return $integration;
	}

	public static function get( string $id ): ?Integration {
		return self::$integrations[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * @return array<string, Integration>
	 */
	public static function all(): array {
		return self::$integrations;
	}

	/**
	 * Handles that must be gated, mapped to their category.
	 *
	 * @return array<string, string> handle => category
	 */
	public static function gated_handles(): array {
		$map = array();
		foreach ( self::$integrations as $integration ) {
			foreach ( $integration->handles as $handle ) {
				$map[ $handle ] = $integration->category;
			}
		}

		return $map;
	}

	/**
	 * Resets the registry. Test helper.
	 */
	public static function reset(): void {
		self::$integrations = array();
	}
}
