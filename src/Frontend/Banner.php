<?php
/**
 * Frontend banner and preference UI.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Frontend;

use Soderlind\Kjeks\Consent\Categories;
use Soderlind\Kjeks\Consent\ConsentSchema;
use Soderlind\Kjeks\Consent\PolicyVersion;
use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

/**
 * Wires the consent banner, preference dialog, and public shortcodes/blocks.
 */
final class Banner {

	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
		add_shortcode( 'kjeks_preferences', array( $this, 'shortcode_preferences' ) );
		add_shortcode( 'kjeks_cookie_declaration', array( $this, 'shortcode_declaration' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function enqueue(): void {
		$asset = $this->asset( 'banner' );

		wp_enqueue_style( 'kjeks-banner', KJEKS_URL . 'build/banner.css', array(), $asset['version'] );
		wp_enqueue_script( 'kjeks-banner', KJEKS_URL . 'build/banner.js', array(), $asset['version'], true );

		wp_localize_script( 'kjeks-banner', 'kjeksConfig', $this->config() );
		wp_set_script_translations( 'kjeks-banner', 'kjeks', KJEKS_DIR . 'languages' );
	}

	/**
	 * Config handed to banner.js. Contains no personal data.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		$network   = new NetworkStore();
		$inventory = new InventoryResolver( new TrackerRegistry(), get_current_blog_id() );
		$content   = ( new ContentResolver( $network ) )->resolve();

		$categories = array();
		foreach ( Categories::all() as $slug => $meta ) {
			$categories[] = array(
				'slug'        => $slug,
				'label'       => $meta['label'],
				'description' => $meta['description'],
				'required'    => $meta['required'],
			);
		}

		return array(
			'cookieName'           => ConsentSchema::COOKIE_NAME,
			'storageKey'           => ConsentSchema::STORAGE_KEY,
			'cookieMonths'         => ConsentSchema::COOKIE_MONTHS,
			'policyVersion'        => PolicyVersion::current(),
			'blogId'               => get_current_blog_id(),
			'secure'               => is_ssl(),
			'honorGpc'             => (bool) apply_filters( 'kjeks_honor_gpc', true ),
			'bannerDefaultVisible' => $network->banner_default_visible(),
			'categories'           => $categories,
			'content'              => $content,
			'declaration'          => $inventory->cached_declaration(),
		);
	}

	public function render(): void {
		// The banner shell is inert until banner.js hydrates it; hidden by default.
		echo '<div id="kjeks-root" hidden></div>' . "\n";
	}

	public function shortcode_preferences(): string {
		$label = esc_html__( 'Cookie settings', 'kjeks' );

		return sprintf(
			'<button type="button" class="kjeks-preferences-link" data-kjeks-open>%s</button>',
			$label
		);
	}

	public function shortcode_declaration(): string {
		$declaration = new CookieDeclaration(
			new InventoryResolver( new TrackerRegistry(), get_current_blog_id() )
		);

		return $declaration->render();
	}

	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'kjeks/preferences',
			array( 'render_callback' => array( $this, 'shortcode_preferences' ) )
		);
		register_block_type(
			'kjeks/cookie-declaration',
			array( 'render_callback' => array( $this, 'shortcode_declaration' ) )
		);
	}

	/**
	 * @return array{version: string}
	 */
	private function asset( string $name ): array {
		$file = KJEKS_DIR . 'build/' . $name . '.asset.php';
		if ( is_readable( $file ) ) {
			$data = include $file;
			if ( is_array( $data ) && isset( $data['version'] ) ) {
				return array( 'version' => (string) $data['version'] );
			}
		}

		return array( 'version' => KJEKS_VERSION );
	}
}
