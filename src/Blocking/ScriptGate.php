<?php
/**
 * Script gate.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Blocking;

/**
 * Emits gated scripts in an inert form so nothing runs before consent.
 *
 * Gating is client-side (ADR 0003): the same HTML is served to everyone and
 * banner.js activates inert scripts once the matching category is granted.
 * Registered `wp_enqueue_script` handles are rewritten to `type="text/plain"`;
 * `src` scripts and inline snippets are printed inert in the footer.
 *
 * A site may opt into server-side conditional enqueue via the
 * `kjeks_server_side_gating` filter, for hosts without full-page caching.
 */
final class ScriptGate {

	public function hooks(): void {
		add_filter( 'script_loader_tag', array( $this, 'make_handle_inert' ), 10, 3 );
		add_action( 'wp_footer', array( $this, 'print_inert_scripts' ), 20 );
	}

	/**
	 * Rewrites a gated handle's script tag to an inert form.
	 *
	 * @param string $tag    The full script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 */
	public function make_handle_inert( string $tag, string $handle, string $src ): string {
		$gated = IntegrationRegistry::gated_handles();

		if ( ! isset( $gated[ $handle ] ) ) {
			return $tag;
		}

		if ( $this->server_side_gating() ) {
			return $tag;
		}

		$category = $gated[ $handle ];

		// Intentionally emits inert markup; the browser runs nothing until consent.
		$replaced = preg_replace(
			'/<script\s/i',
			sprintf(
				'<script type="text/plain" data-kjeks-category="%s" data-kjeks-src="%s" ', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
				esc_attr( $category ),
				esc_url( $src )
			),
			$tag,
			1
		);

		return is_string( $replaced ) ? $replaced : $tag;
	}

	/**
	 * Prints inert `src` scripts and inline snippets from all integrations.
	 */
	public function print_inert_scripts(): void {
		foreach ( IntegrationRegistry::all() as $integration ) {
			foreach ( $integration->src_scripts as $script ) {
				$this->print_inert_src( $integration->category, $integration->id, $script['src'], $script['attrs'] );
			}

			foreach ( $integration->inline as $index => $code ) {
				$this->print_inert_inline( $integration->category, $integration->id . '-' . $index, $code );
			}
		}
	}

	/**
	 * @param array<string, string> $attrs Extra attributes.
	 */
	private function print_inert_src( string $category, string $id, string $src, array $attrs ): void {
		$attr_html = '';
		foreach ( $attrs as $key => $value ) {
			$attr_html .= sprintf( ' data-attr-%s="%s"', esc_attr( sanitize_key( $key ) ), esc_attr( $value ) );
		}

		printf(
			'<script type="text/plain" data-kjeks-category="%1$s" data-kjeks-integration="%2$s" data-kjeks-src="%3$s"%4$s></script>' . "\n", // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			esc_attr( $category ),
			esc_attr( $id ),
			esc_url( $src ),
			// Attributes are individually escaped above.
			$attr_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	private function print_inert_inline( string $category, string $id, string $code ): void {
		printf(
			'<script type="text/plain" data-kjeks-category="%1$s" data-kjeks-integration="%2$s">%3$s</script>' . "\n", // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			esc_attr( $category ),
			esc_attr( $id ),
			// Inline snippet is developer-provided and executed only after consent.
			$code // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	private function server_side_gating(): bool {
		/**
		 * Filters whether gating is done server-side (conditional enqueue).
		 *
		 * Default false keeps output identical for every visitor so full-page
		 * caches are not fragmented. Enable only on uncached sites.
		 *
		 * @param bool $enabled Whether to gate server-side.
		 */
		return (bool) apply_filters( 'kjeks_server_side_gating', false );
	}
}
