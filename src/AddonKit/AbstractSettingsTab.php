<?php
/**
 * Add-on kit: settings-tab base class.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

use Soderlind\Kjeks\Admin\NetworkAdmin;

/**
 * Base class for an add-on's settings tab.
 *
 * Mirrors the tab-shell contract exposed by the core "Cookie Consent" screen:
 * the add-on registers a tab through the `kjeks_settings_tabs` filter, enqueues
 * its own bundle through the `kjeks_settings_enqueue_scripts` action (only when
 * its tab is active), and mounts a React app into a container the base renders.
 *
 * When the core plugin is absent or does not support tabs, the same class
 * registers a standalone admin page so the add-on degrades gracefully.
 *
 * Sub-classes provide a handful of getters; everything else (tab registration,
 * asset enqueue, localisation, the standalone fallback) is provided here.
 *
 * Public, versioned API.
 *
 * @since 1.3.0
 */
abstract class AbstractSettingsTab {

	// ------------------------------------------------------------------
	// Abstract — each add-on must provide these.
	// ------------------------------------------------------------------

	/**
	 * Tab slug used in `kjeks_settings_tabs` (e.g. 'embeds').
	 */
	abstract protected function get_tab_slug(): string;

	/**
	 * Translated tab label shown in the UI.
	 */
	abstract protected function get_tab_label(): string;

	/**
	 * Plugin text domain for translations.
	 */
	abstract protected function get_text_domain(): string;

	/**
	 * Absolute path to the plugin's build/ directory (trailing slash).
	 */
	abstract protected function get_build_path(): string;

	/**
	 * URL to the plugin's build/ directory (trailing slash).
	 */
	abstract protected function get_build_url(): string;

	/**
	 * Absolute path to the plugin's languages/ directory.
	 */
	abstract protected function get_languages_path(): string;

	/**
	 * Plugin version, used as a fallback when the asset file is missing.
	 */
	abstract protected function get_plugin_version(): string;

	/**
	 * JS global variable name for wp_localize_script (e.g. 'kjeksEmbeds').
	 */
	abstract protected function get_localized_name(): string;

	/**
	 * Data passed to wp_localize_script. Should include 'restUrl' and 'nonce'.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function get_localized_data(): array;

	// ------------------------------------------------------------------
	// Optional overrides.
	// ------------------------------------------------------------------

	/**
	 * Asset entry-point basename (without extension). Default: 'index'.
	 */
	protected function get_asset_entry(): string {
		return 'index';
	}

	/**
	 * Extra CSS style dependencies for the main style. Default: ['wp-components'].
	 *
	 * @return string[]
	 */
	protected function get_style_deps(): array {
		return array( 'wp-components' );
	}

	/**
	 * Tab registration array. Override to add 'subtabs' or extra keys.
	 *
	 * @return array{title: string, callback: callable, subtabs?: array<string, string>}
	 */
	protected function get_tab_definition(): array {
		return array(
			'title'    => $this->get_tab_label(),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	/**
	 * Capability required to view the tab and the standalone fallback page.
	 */
	protected function get_menu_capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * HTML id for the React mount-point div.
	 */
	protected function get_app_container_id(): string {
		return 'kjeks-' . $this->get_tab_slug() . '-app';
	}

	// ------------------------------------------------------------------
	// Concrete public API — wired by the add-on's Plugin class.
	// ------------------------------------------------------------------

	/**
	 * Wire the tab (when core supports tabs) or the standalone fallback.
	 */
	public function hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( self::core_supports_tabs() ) {
			add_filter( 'kjeks_settings_tabs', array( $this, 'register_tab' ) );
			add_action( 'kjeks_settings_enqueue_scripts', array( $this, 'enqueue_tab_scripts' ), 10, 2 );
			return;
		}

		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Whether the core "Cookie Consent" screen exposes the tab shell.
	 */
	public static function core_supports_tabs(): bool {
		if ( ! class_exists( NetworkAdmin::class ) ) {
			return false;
		}

		$flag = NetworkAdmin::class . '::SUPPORTS_ADDON_TABS';

		return defined( $flag ) && true === constant( $flag );
	}

	/**
	 * Register the tab entry with the core shell.
	 *
	 * Callback for the `kjeks_settings_tabs` filter.
	 *
	 * @param array<string, mixed> $tabs Existing tabs.
	 * @return array<string, mixed>
	 */
	public function register_tab( array $tabs ): array {
		$tabs[ $this->get_tab_slug() ] = $this->get_tab_definition();

		return $tabs;
	}

	/**
	 * Enqueue assets when this tab is active.
	 *
	 * Callback for the `kjeks_settings_enqueue_scripts` action.
	 *
	 * @param string $active_tab    Currently active tab slug.
	 * @param string $active_subtab Currently active subtab slug.
	 */
	public function enqueue_tab_scripts( string $active_tab, string $active_subtab ): void {
		unset( $active_subtab );

		if ( $this->get_tab_slug() !== $active_tab ) {
			return;
		}

		$this->do_enqueue_assets();
	}

	/**
	 * Render the tab body (a React mount-point by default).
	 *
	 * @param string $active_tab    Currently active tab slug.
	 * @param string $active_subtab Currently active subtab slug.
	 */
	public function render_tab( string $active_tab, string $active_subtab ): void {
		unset( $active_tab, $active_subtab );
		?>
		<div class="kjeks-tab-content">
			<div id="<?php echo esc_attr( $this->get_app_container_id() ); ?>"></div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Standalone fallback (core absent or tabs unsupported).
	// ------------------------------------------------------------------

	/**
	 * Register a standalone top-level admin page.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			$this->get_tab_label(),
			$this->get_tab_label(),
			$this->get_menu_capability(),
			$this->standalone_slug(),
			array( $this, 'render_admin_page' ),
			'dashicons-privacy'
		);
	}

	/**
	 * Render the standalone admin page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( $this->get_menu_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_tab_label() ); ?></h1>
			<div class="kjeks-tab-content">
				<div id="<?php echo esc_attr( $this->get_app_container_id() ); ?>"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue assets for the standalone admin page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( $this->standalone_hook_suffix() !== $hook_suffix ) {
			return;
		}

		$this->do_enqueue_assets();
	}

	// ------------------------------------------------------------------
	// Internal asset enqueue.
	// ------------------------------------------------------------------

	/**
	 * Enqueue the bundle, its stylesheet, translations, and localised data.
	 */
	protected function do_enqueue_assets(): void {
		$entry      = $this->get_asset_entry();
		$build_path = $this->get_build_path();
		$build_url  = $this->get_build_url();
		$handle     = 'kjeks-' . $this->get_tab_slug() . '-admin';

		$asset_file = $build_path . $entry . '.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			$handle,
			$build_url . $entry . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, $this->get_text_domain(), $this->get_languages_path() );

		$css_file = $build_path . $entry . '.css';
		if ( is_readable( $css_file ) ) {
			wp_enqueue_style(
				$handle,
				$build_url . $entry . '.css',
				$this->get_style_deps(),
				$asset['version']
			);
		}

		wp_localize_script( $handle, $this->get_localized_name(), $this->get_localized_data() );
	}

	/**
	 * Slug for the standalone fallback page.
	 */
	private function standalone_slug(): string {
		return 'kjeks-' . $this->get_tab_slug();
	}

	/**
	 * Admin page hook suffix produced by the standalone top-level menu.
	 */
	private function standalone_hook_suffix(): string {
		return 'toplevel_page_' . $this->standalone_slug();
	}
}
