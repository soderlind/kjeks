<?php
/**
 * Add-on kit: network/site settings screen scaffold.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

use Soderlind\Kjeks\Admin\NetworkAdmin;

/**
 * Base for an add-on's single settings screen.
 *
 * Handles the Multisite/single-site split for free: on Multisite the option is
 * stored network-wide, on single site it is stored per site. The screen mounts
 * as a sub-item of the core Kjeks "Cookie Consent" menu in both cases.
 * Sub-classes declare the option key, menu slug, titles, defaults,
 * normalisation, and the form fields; everything else (menus, nonces, saving,
 * resolution) is provided here.
 *
 * Public, versioned API.
 *
 * @since 1.2.0
 */
abstract class SettingsPage {

	abstract protected function option_key(): string;

	abstract protected function menu_slug(): string;

	abstract protected function page_title(): string;

	/**
	 * Short label for the menu entry (e.g. "Embeds"). Defaults to the page title.
	 */
	protected function menu_title(): string {
		return $this->page_title();
	}

	/**
	 * Slug of the parent menu the screen mounts under.
	 */
	protected function parent_slug(): string {
		return NetworkAdmin::SLUG;
	}

	/**
	 * Default, normalised configuration.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function defaults(): array;

	/**
	 * Normalises raw values (submitted or stored) into the config shape.
	 *
	 * Must be idempotent: it runs on submitted form values and on values read
	 * back from storage.
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed>
	 */
	abstract protected function normalize( array $raw ): array;

	/**
	 * Renders the form rows (inside the kit-provided `<form>`).
	 *
	 * @param string               $prefix Field-name prefix; use field_name().
	 * @param array<string, mixed>  $config Effective config to pre-fill.
	 */
	abstract protected function render_fields( string $prefix, array $config ): void;

	/**
	 * Effective, filtered configuration for the current context.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve(): array {
		$config = $this->normalize( Options::read( $this->option_key() ) );

		/**
		 * Filters the resolved add-on configuration.
		 *
		 * @param array<string, mixed> $config Resolved config.
		 */
		return (array) apply_filters( $this->option_key() . '_config', $config );
	}

	public function hooks(): void {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_post_' . $this->action(), array( $this, 'save' ) );
			return;
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function menu(): void {
		if ( is_multisite() ) {
			add_submenu_page(
				$this->parent_slug(),
				$this->page_title(),
				$this->menu_title(),
				'manage_network_options',
				$this->menu_slug(),
				array( $this, 'render_network_page' )
			);
			return;
		}

		add_submenu_page(
			$this->parent_slug(),
			$this->page_title(),
			$this->menu_title(),
			'manage_options',
			$this->menu_slug(),
			array( $this, 'render_site_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			$this->option_key(),
			$this->option_key(),
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_setting' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input Raw submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_setting( mixed $input ): array {
		return $this->normalize( is_array( $input ) ? $input : array() );
	}

	public function render_site_page(): void {
		$config = $this->resolve();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( $this->option_key() ); ?>
				<?php $this->render_fields( $this->option_key(), $config ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_network_page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}

		$config = $this->resolve();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->page_title() ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $this->action() ); ?>" />
				<?php wp_nonce_field( $this->action() ); ?>
				<?php $this->render_fields( '', $config ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'kjeks' ) );
		}

		check_admin_referer( $this->action() );

		$values = $this->normalize( wp_unslash( $_POST ) );

		Options::write( $this->option_key(), $values );

		wp_safe_redirect( add_query_arg( 'updated', '1', network_admin_url( 'admin.php?page=' . $this->menu_slug() ) ) );
		exit;
	}

	/**
	 * Builds a field-name attribute for the current form context.
	 *
	 * On single site fields nest under the option key (`option[key]`); on the
	 * network form they are top-level POST keys (`key`).
	 */
	protected function field_name( string $prefix, string $key ): string {
		return '' === $prefix ? $key : $prefix . '[' . $key . ']';
	}

	private function action(): string {
		return str_replace( '-', '_', $this->menu_slug() ) . '_save';
	}
}
