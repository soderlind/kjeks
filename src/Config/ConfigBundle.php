<?php
/**
 * Portable config bundle (export / apply).
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Config;

use RuntimeException;
use Soderlind\Kjeks\Consent\PolicyVersion;
use Soderlind\Kjeks\Inventory\NetworkStore;

/**
 * Serializes an install's authored settings into a portable Config bundle and
 * applies one to a target install (see CONTEXT.md "Config bundle").
 *
 * The bundle carries authored settings only — never derived scan Observations,
 * secrets, or visitor Consent records. Core owns the envelope; add-ons register
 * their own section through the `kjeks_config_sections` filter. Each section
 * replaces its option wholesale, except the Registry which merges by Identity
 * (ADR 0007). Applying a bundle bumps the target policy version so visitors are
 * re-prompted (ADR 0004).
 */
final class ConfigBundle {

	public const SCHEMA = 1;

	public function __construct(
		private readonly NetworkStore $network = new NetworkStore(),
		private readonly RegistryTransfer $registry = new RegistryTransfer(),
	) {}

	/**
	 * The sections a bundle carries, keyed by option name.
	 *
	 * @return array<string, array{version: int, export: callable, apply: callable}>
	 */
	private function sections(): array {
		$network  = $this->network;
		$registry = $this->registry;

		$sections = array(
			'kjeks_network_content'  => array(
				'version' => 1,
				'export'  => static fn (): array => $network->content(),
				'apply'   => static function ( array $data ) use ( $network ): void {
					$network->save_content( $data );
				},
			),
			'kjeks_network_settings' => array(
				'version' => 1,
				'export'  => static fn (): array => $network->settings(),
				'apply'   => static function ( array $data ) use ( $network ): void {
					$network->save_settings( $data );
				},
			),
			'kjeks_network_trackers' => array(
				'version' => 1,
				'export'  => static fn (): array => $registry->export(),
				'apply'   => static function ( array $data ) use ( $registry ): void {
					$registry->merge( $data );
				},
			),
		);

		/**
		 * Filters the sections a Config bundle carries.
		 *
		 * Add-ons append their own entry keyed by option name. Each section is
		 * `[ 'version' => int, 'export' => callable(): ?array, 'apply' => callable(array): void ]`.
		 * An `export` returning null or an empty array omits the section; an
		 * absent section on apply is skipped. `apply` replaces the option
		 * wholesale unless the section implements its own merge.
		 *
		 * @param array<string, array{version: int, export: callable, apply: callable}> $sections Registered sections.
		 */
		$sections = apply_filters( 'kjeks_config_sections', $sections );

		return is_array( $sections ) ? $sections : array();
	}

	/**
	 * Builds a portable bundle of this install's authored settings.
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		$out = array();

		foreach ( $this->sections() as $id => $section ) {
			if ( ! is_array( $section ) || ! isset( $section['export'] ) || ! is_callable( $section['export'] ) ) {
				continue;
			}

			$data = call_user_func( $section['export'] );
			if ( null === $data || array() === $data ) {
				continue;
			}

			$out[ (string) $id ] = array(
				'version' => (int) ( $section['version'] ?? 1 ),
				'data'    => $data,
			);
		}

		return array(
			'schema'       => self::SCHEMA,
			'generated_at' => gmdate( 'c' ),
			'source'       => array(
				'url'          => is_multisite() ? network_home_url() : home_url(),
				'is_multisite' => is_multisite(),
			),
			'sections'     => $out,
		);
	}

	/**
	 * Applies a bundle to this install.
	 *
	 * @param array<string, mixed> $bundle Decoded bundle.
	 * @return array{applied: list<string>, skipped: list<string>, warnings: list<string>, policy_version: int}
	 *
	 * @throws RuntimeException When the bundle schema is unsupported.
	 */
	public function apply( array $bundle ): array {
		$schema = isset( $bundle['schema'] ) ? (int) $bundle['schema'] : 0;
		if ( self::SCHEMA !== $schema ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
						/* translators: 1: bundle schema, 2: supported schema. */
						__( 'Unsupported config bundle schema %1$d (this install understands %2$d).', 'kjeks' ),
						$schema,
						self::SCHEMA
					)
				)
			);
		}

		$applied  = array();
		$skipped  = array();
		$warnings = array();

		$sections = $this->sections();
		$incoming = ( isset( $bundle['sections'] ) && is_array( $bundle['sections'] ) ) ? $bundle['sections'] : array();

		foreach ( $incoming as $id => $section ) {
			$id = (string) $id;

			if ( ! isset( $sections[ $id ] ) || ! is_array( $section ) || ! isset( $section['data'] ) || ! is_array( $section['data'] ) ) {
				$skipped[] = $id;
				continue;
			}

			$their_version = isset( $section['version'] ) ? (int) $section['version'] : 1;
			$our_version   = (int) ( $sections[ $id ]['version'] ?? 1 );
			if ( $their_version > $our_version ) {
				$warnings[] = sprintf(
					/* translators: 1: section id, 2: bundle version, 3: supported version. */
					__( 'Section %1$s is version %2$d; this install understands %3$d. Skipped.', 'kjeks' ),
					$id,
					$their_version,
					$our_version
				);
				$skipped[] = $id;
				continue;
			}

			call_user_func( $sections[ $id ]['apply'], $section['data'] );
			$applied[] = $id;
		}

		// Applying a config is a policy change: re-prompt visitors (ADR 0004).
		$version = PolicyVersion::bump();

		return array(
			'applied'        => $applied,
			'skipped'        => $skipped,
			'warnings'       => $warnings,
			'policy_version' => $version,
		);
	}
}
