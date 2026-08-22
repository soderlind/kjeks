<?php
/**
 * Public developer API.
 *
 * Thin, documented wrappers around the blocking layer so integrations don't
 * depend on internal classes.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Blocking\EmbedGate;
use Soderlind\Kjeks\Blocking\IntegrationRegistry;
use Soderlind\Kjeks\Consent\ConsentState;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'kjeks_register_integration' ) ) {
	/**
	 * Registers an integration against a consent category.
	 *
	 * @param string               $id   Unique integration id.
	 * @param array<string, mixed> $args category, label, handles, src_scripts, inline.
	 */
	function kjeks_register_integration( string $id, array $args ): void {
		IntegrationRegistry::register( $id, $args );
	}
}

if ( ! function_exists( 'kjeks_enqueue_script' ) ) {
	/**
	 * Enqueues a script and gates it behind a consent category.
	 *
	 * The script is registered normally but emitted inert until consent
	 * (see ScriptGate), which keeps output cache-friendly.
	 *
	 * @param string       $handle Script handle.
	 * @param string       $src    Script URL.
	 * @param list<string> $deps   Dependencies.
	 */
	function kjeks_enqueue_script( string $handle, string $src, string $category, array $deps = array() ): void {
		wp_enqueue_script( $handle, $src, $deps, KJEKS_VERSION, true );
		IntegrationRegistry::register(
			'handle-' . $handle,
			array(
				'category' => $category,
				'label'    => $handle,
				'handles'  => array( $handle ),
			)
		);
	}
}

if ( ! function_exists( 'kjeks_add_inline_script' ) ) {
	/**
	 * Delays an inline script until consent for the category is granted.
	 *
	 * @param string $category Consent category.
	 * @param string $code     JavaScript source (without <script> tags).
	 * @param string $id       Optional integration id.
	 */
	function kjeks_add_inline_script( string $category, string $code, string $id = '' ): void {
		$id = '' !== $id ? $id : 'inline-' . substr( md5( $code ), 0, 8 );
		IntegrationRegistry::register(
			$id,
			array(
				'category' => $category,
				'inline'   => array( $code ),
			)
		);
	}
}

if ( ! function_exists( 'kjeks_embed' ) ) {
	/**
	 * Returns accessible, consent-gated embed markup.
	 *
	 * @param string               $src      Embed URL.
	 * @param string               $category Consent category.
	 * @param array<string, mixed> $args     title, width, height, provider.
	 */
	function kjeks_embed( string $src, string $category, array $args = array() ): string {
		return ( new EmbedGate() )->render( $src, $category, $args );
	}
}

if ( ! function_exists( 'kjeks_is_granted' ) ) {
	/**
	 * Whether a category is granted for the current request (server-side view).
	 */
	function kjeks_is_granted( string $category ): bool {
		static $state = null;
		if ( ! $state instanceof ConsentState ) {
			$state = new ConsentState();
		}

		return $state->is_granted( $category );
	}
}

if ( ! function_exists( 'kjeks_preferences_link' ) ) {
	/**
	 * Prints or returns a link that reopens the consent preferences.
	 *
	 * @param string $label   Link text.
	 * @param bool   $display Whether to echo.
	 */
	function kjeks_preferences_link( string $label = '', bool $display = true ): string {
		$label = '' !== $label ? $label : __( 'Cookie settings', 'kjeks' );
		$html  = sprintf(
			'<button type="button" class="kjeks-preferences-link" data-kjeks-open>%s</button>',
			esc_html( $label )
		);

		if ( $display ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		return $html;
	}
}
