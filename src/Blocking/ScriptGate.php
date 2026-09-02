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
 * Registered `wp_enqueue_script` handles and external `src` scripts are both
 * enqueued normally, then rewritten to `type="text/plain"` via
 * `script_loader_tag`; only inline snippets are printed inert in the footer.
 *
 * A site may opt into server-side conditional enqueue via the
 * `kjeks_server_side_gating` filter, for hosts without full-page caching.
 */
final class ScriptGate {

	/**
	 * Enqueued src-script handles => their gating metadata.
	 *
	 * @var array<string, array{category: string, integration: string, attrs: array<string, string>}>
	 */
	private array $src_meta = array();

	public function hooks(): void {
		add_filter( 'script_loader_tag', array( $this, 'make_handle_inert' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_src_scripts' ) );
		add_action( 'wp_footer', array( $this, 'print_inert_scripts' ), 20 );
	}

	/**
	 * Enqueues each integration's external `src` scripts as real handles so the
	 * `script_loader_tag` filter can rewrite them inert (no raw `<script>` echo).
	 */
	public function enqueue_src_scripts(): void {
		foreach ( IntegrationRegistry::all() as $integration ) {
			foreach ( $integration->src_scripts as $index => $script ) {
				$handle = 'kjeks-' . $integration->id . '-src-' . (string) $index;
				// version null: leave the third-party URL untouched (no ?ver arg).
				wp_enqueue_script( $handle, $script['src'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Third-party consent-gated URL; appending ?ver would alter the vendor request.
				$this->src_meta[ $handle ] = array(
					'category'    => $integration->category,
					'integration' => $integration->id,
					'attrs'       => $script['attrs'],
				);
			}
		}
	}

	/**
	 * Rewrites a gated handle's script tag to an inert form.
	 *
	 * @param string $tag    The full script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 */
	public function make_handle_inert( string $tag, string $handle, string $src ): string {
		// External src scripts enqueued by this gate: always inert (client gate),
		// regardless of server-side gating.
		if ( isset( $this->src_meta[ $handle ] ) ) {
			$meta = $this->src_meta[ $handle ];
			return $this->to_inert( $tag, $meta['category'], $src, $meta['integration'], $meta['attrs'] );
		}

		$gated = IntegrationRegistry::gated_handles();

		if ( ! isset( $gated[ $handle ] ) ) {
			return $tag;
		}

		if ( $this->server_side_gating() ) {
			return $tag;
		}

		return $this->to_inert( $tag, $gated[ $handle ], $src, '', array() );
	}

	/**
	 * Rewrites a script tag's opening `<script` to an inert placeholder.
	 *
	 * @param array<string, string> $attrs Extra data-attr-* pairs to carry.
	 */
	private function to_inert( string $tag, string $category, string $src, string $integration, array $attrs ): string {
		// Intentionally emits inert markup; the browser runs nothing until consent.
		$replaced = preg_replace(
			'/<script\s/i',
			$this->inert_open_tag( $category, $src, $integration, $attrs ),
			$tag,
			1
		);

		return is_string( $replaced ) ? $replaced : $tag;
	}

	/**
	 * Builds the inert opening `<script ` with gate + optional attributes.
	 *
	 * @param array<string, string> $attrs Extra data-attr-* pairs to carry.
	 */
	private function inert_open_tag( string $category, string $src, string $integration, array $attrs ): string {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Inert type="text/plain" placeholder rewritten onto an already-enqueued handle; activated client-side after consent.
		$out = '<script type="text/plain" data-kjeks-category="' . esc_attr( $category ) . '"';
		if ( '' !== $integration ) {
			$out .= ' data-kjeks-integration="' . esc_attr( $integration ) . '"';
		}
		if ( '' !== $src ) {
			$out .= ' data-kjeks-src="' . esc_url( $src ) . '"';
		}
		foreach ( $attrs as $key => $value ) {
			$out .= ' data-attr-' . esc_attr( sanitize_key( (string) $key ) ) . '="' . esc_attr( $value ) . '"';
		}

		return $out . ' ';
	}

	/**
	 * Prints inline snippets from all integrations in an inert form.
	 *
	 * `src` scripts go through the enqueue system (see enqueue_src_scripts);
	 * inline snippets have no enqueue equivalent that stays inert, so they are
	 * emitted here as inert placeholders.
	 */
	public function print_inert_scripts(): void {
		foreach ( IntegrationRegistry::all() as $integration ) {
			foreach ( $integration->inline as $index => $code ) {
				$this->print_inert_inline( $integration->category, $integration->id . '-' . $index, $code );
			}
		}
	}

	private function print_inert_inline( string $category, string $id, string $code ): void {
		// esc_html() prevents a </script> breakout in the served markup; banner.js
		// reads textContent, which decodes the entities back to the original snippet.
		printf(
			'<script type="text/plain" data-kjeks-category="%1$s" data-kjeks-integration="%2$s">%3$s</script>' . "\n", // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Inert type="text/plain" placeholder for consent gating; wp_add_inline_script() emits runnable JS, so no enqueue API can produce an inert inline node.
			esc_attr( $category ),
			esc_attr( $id ),
			esc_html( $code )
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
