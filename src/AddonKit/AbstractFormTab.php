<?php
/**
 * Add-on kit: server-rendered settings tab.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

/**
 * Base for an add-on whose settings are a plain PHP form shown as a tab on the
 * core "Cookie Consent" screen.
 *
 * Unlike {@see AbstractSettingsTab} (which mounts a React app), this renders the
 * add-on's `form-table` fields directly and saves through admin-post. When the
 * core tab shell is unavailable it falls back to a standalone admin menu.
 *
 * Handles the Multisite/single-site option split via {@see Options}. Sub-classes
 * declare the slug, label, option key, defaults, normalisation, and fields;
 * menus, nonces, saving, and the updated notice are provided here.
 *
 * Public, versioned API.
 *
 * @since 1.3.0
 */
abstract class AbstractFormTab {

	abstract protected function get_tab_slug(): string;

	abstract protected function get_tab_label(): string;

	abstract protected function option_key(): string;

	/**
	 * Default, normalised configuration.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function defaults(): array;

	/**
	 * Normalises raw values (submitted or stored) into the config shape.
	 *
	 * Must be idempotent: it runs on submitted form values and on stored values.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>
	 */
	abstract protected function normalize( array $raw ): array;

	/**
	 * Renders the form rows (inside the kit-provided `<form>`).
	 *
	 * Field names are top-level POST keys; use {@see field_name()}.
	 *
	 * @param string               $prefix Field-name prefix (always '').
	 * @param array<string, mixed> $config Effective config to pre-fill.
	 */
	abstract protected function render_fields( string $prefix, array $config ): void;

	/**
	 * Capability required to view and save.
	 */
	protected function get_menu_capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Effective, filtered configuration for the current context.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve(): array {
		$config = $this->normalize( Options::read( $this->option_key() ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- option_key() is an add-on's kjeks-prefixed option name by convention (e.g. "kjeks_embeds").
		return (array) apply_filters( $this->option_key() . '_config', $config );
	}

	/**
	 * Wire the tab (when core supports tabs) or the standalone fallback. The
	 * save handler is registered in both cases.
	 */
	public function hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_post_' . $this->action(), array( $this, 'save' ) );

		if ( AbstractSettingsTab::core_supports_tabs() ) {
			add_filter( 'kjeks_settings_tabs', array( $this, 'register_tab' ) );
			return;
		}

		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register the tab entry with the core shell.
	 *
	 * @param array<string, mixed> $tabs Existing tabs.
	 * @return array<string, mixed>
	 */
	public function register_tab( array $tabs ): array {
		$tabs[ $this->get_tab_slug() ] = array(
			'title'    => $this->get_tab_label(),
			'callback' => array( $this, 'render_tab' ),
		);

		return $tabs;
	}

	/**
	 * Render the tab body (the settings form).
	 *
	 * @param string $active_tab    Active tab slug.
	 * @param string $active_subtab Active subtab slug.
	 */
	public function render_tab( string $active_tab, string $active_subtab ): void {
		unset( $active_tab, $active_subtab );

		$this->maybe_updated_notice();
		$this->render_intro();
		$this->render_form();
	}

	/**
	 * Optional lead paragraph shown in a card above the settings form.
	 *
	 * @return string Plain-text intro, or '' to omit the intro card.
	 */
	protected function get_tab_intro(): string {
		return '';
	}

	/**
	 * Render the intro card (tab title plus lead paragraph).
	 */
	protected function render_intro(): void {
		$intro = $this->get_tab_intro();
		if ( '' === $intro ) {
			return;
		}

		printf(
			'<div class="kjeks-card kjeks-card--intro"><h2>%s</h2><p>%s</p></div>',
			esc_html( $this->get_tab_label() ),
			esc_html( $intro )
		);
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
			'kjeks-' . $this->get_tab_slug(),
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
		<div class="wrap kjeks-settings">
			<h1><?php echo esc_html( $this->get_tab_label() ); ?></h1>
			<?php
			$this->maybe_updated_notice();
			$this->render_intro();
			$this->render_form();
			?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Saving.
	// ------------------------------------------------------------------

	public function save(): void {
		if ( ! current_user_can( $this->get_menu_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'kjeks' ) );
		}

		check_admin_referer( $this->action() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$values = $this->normalize( wp_unslash( $_POST ) );

		Options::write( $this->option_key(), $values );

		wp_safe_redirect( add_query_arg( 'updated', '1', $this->return_url() ) );
		exit;
	}

	/**
	 * Render the settings form.
	 */
	protected function render_form(): void {
		$config = $this->resolve();
		?>
		<form class="kjeks-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $this->action() ); ?>" />
			<?php wp_nonce_field( $this->action() ); ?>
			<?php $this->render_fields( '', $config ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Show a "settings saved" notice after a redirect.
	 */
	protected function maybe_updated_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['updated'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Settings saved.', 'kjeks' )
		);
	}

	/**
	 * Builds a field-name attribute. Fields are top-level POST keys.
	 */
	protected function field_name( string $prefix, string $key ): string {
		return '' === $prefix ? $key : $prefix . '[' . $key . ']';
	}

	/**
	 * URL to return to after saving (the tab, or the standalone page).
	 */
	private function return_url(): string {
		$slug = AbstractSettingsTab::core_supports_tabs() ? \Soderlind\Kjeks\Admin\NetworkAdmin::SLUG : 'kjeks-' . $this->get_tab_slug();
		$args = array( 'page' => $slug );

		if ( AbstractSettingsTab::core_supports_tabs() ) {
			$args['tab'] = $this->get_tab_slug();
		}

		$url = add_query_arg( $args, 'admin.php' );

		return is_multisite() ? network_admin_url( $url ) : admin_url( $url );
	}

	/**
	 * Admin-post action name.
	 */
	private function action(): string {
		return 'kjeks_' . str_replace( '-', '_', $this->get_tab_slug() ) . '_save';
	}
}
