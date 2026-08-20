<?php
/**
 * Auto-appends the cookie declaration to the privacy policy page.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Privacy;

use Soderlind\Kjeks\Frontend\CookieDeclaration;
use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

/**
 * When the network opt-in is on, appends the live cookie declaration to the
 * site's privacy policy page — unless the block or shortcode already rendered
 * it (dedup guard keyed on the declaration wrapper class).
 */
final class PrivacyPageDeclaration {

	public function __construct( private readonly NetworkStore $network ) {}

	public function hooks(): void {
		add_filter( 'the_content', array( $this, 'append' ), 20 );
	}

	public function append( string $content ): string {
		if ( ! $this->is_privacy_page_main_content() ) {
			return $content;
		}
		if ( ! $this->should_render( $content ) ) {
			return $content;
		}

		return $content . $this->render();
	}

	/**
	 * Whether the declaration should be appended to the given content.
	 *
	 * Requires the opt-in and skips when a declaration is already present.
	 */
	public function should_render( string $content ): bool {
		if ( ! $this->network->privacy_page_declaration() ) {
			return false;
		}

		return ! str_contains( $content, 'kjeks-declaration' );
	}

	private function is_privacy_page_main_content(): bool {
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );

		return 0 !== $privacy_id
			&& is_page( $privacy_id )
			&& is_main_query()
			&& in_the_loop();
	}

	protected function render(): string {
		$declaration = new CookieDeclaration(
			new InventoryResolver( new TrackerRegistry(), get_current_blog_id() )
		);

		return $declaration->render();
	}
}
