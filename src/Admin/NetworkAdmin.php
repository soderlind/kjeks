<?php
/**
 * Network admin review screen — add-on tab shell.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Admin;

/**
 * Registers the "Cookie Consent" screen as a tab shell and mounts the React apps.
 *
 * The screen is a PHP-rendered tab shell (mirrors the settings framework shared
 * across the Kjeks family): the core contributes the built-in "Cookies" and
 * "Banner" tabs, and add-ons register their own tabs through the
 * `kjeks_settings_tabs` filter, enqueueing their bundles through the
 * `kjeks_settings_enqueue_scripts` action. Site-wide flags and the scanner key
 * live on a separate "Settings" submenu.
 */
final class NetworkAdmin {

	public const SLUG = 'kjeks-network';

	/**
	 * Settings submenu slug (site-wide flags + scanner key).
	 */
	public const SETTINGS_SLUG = 'kjeks-settings';

	/**
	 * Marks this screen as a tab host. Add-ons check this to decide whether to
	 * register a tab or fall back to a standalone admin page.
	 */
	public const SUPPORTS_ADDON_TABS = true;

	/**
	 * Hook suffix of the Settings submenu, captured on registration.
	 */
	private string $settings_hook = '';

	public function hooks(): void {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Capability to administer consent: network-wide on multisite, site admin on single-site.
	 */
	public function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	public function menu(): void {
		add_menu_page(
			__( 'Cookie Consent', 'kjeks' ),
			__( 'Cookie Consent', 'kjeks' ),
			$this->capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-privacy'
		);

		$this->settings_hook = (string) add_submenu_page(
			self::SLUG,
			__( 'Settings', 'kjeks' ),
			__( 'Settings', 'kjeks' ),
			$this->capability(),
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render the tab shell.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}

		$active_tab    = $this->active_tab();
		$active_subtab = $this->active_subtab();

		// Core built-in tabs first, then alphabetically-sorted add-on tabs.
		$tabs = array(
			'cookies' => array(
				'title'    => __( 'Cookies', 'kjeks' ),
				'callback' => array( $this, 'render_core_tab' ),
			),
			'banner'  => array(
				'title'    => __( 'Banner', 'kjeks' ),
				'callback' => array( $this, 'render_core_tab' ),
			),
		);

		/**
		 * Filter to register add-on tabs on the Cookie Consent screen.
		 *
		 * Add-ons register a tab keyed by slug. Tabs may optionally include a
		 * 'subtabs' array (`[ 'subtab-slug' => 'Subtab Title', ... ]`) for
		 * secondary navigation.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, array{title: string, callback: callable, subtabs?: array<string, string>}> $tabs Registered tabs.
		 */
		$addon_tabs = apply_filters( 'kjeks_settings_tabs', array() );

		uasort(
			$addon_tabs,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
			}
		);

		$tabs = array_merge( $tabs, $addon_tabs );

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'cookies';
		}

		$subtabs = $tabs[ $active_tab ]['subtabs'] ?? array();
		if ( ! empty( $subtabs ) && ( '' === $active_subtab || ! isset( $subtabs[ $active_subtab ] ) ) ) {
			$active_subtab = (string) array_key_first( $subtabs );
		}

		$base_url = $this->page_url( self::SLUG );
		?>
		<div class="wrap kjeks-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( count( $tabs ) > 1 ) : ?>
				<nav class="nav-tab-wrapper kjeks-nav-tabs">
					<?php foreach ( $tabs as $slug => $tab ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
							class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html( $tab['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if ( ! empty( $subtabs ) ) : ?>
				<nav class="kjeks-subtab-nav">
					<?php
					foreach ( $subtabs as $subtab_slug => $subtab_title ) :
						$subtab_url = add_query_arg(
							array(
								'tab'    => $active_tab,
								'subtab' => $subtab_slug,
							),
							$base_url
						);
						?>
						<a href="<?php echo esc_url( $subtab_url ); ?>"
							class="kjeks-subtab-link <?php echo $active_subtab === $subtab_slug ? 'is-active' : ''; ?>">
							<?php echo esc_html( $subtab_title ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<div class="kjeks-tab-content">
				<?php
				if ( is_callable( $tabs[ $active_tab ]['callback'] ) ) {
					call_user_func( $tabs[ $active_tab ]['callback'], $active_tab, $active_subtab );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a core tab body (the React review app mounts here).
	 *
	 * @param string $active_tab    Active tab slug.
	 * @param string $active_subtab Active subtab slug.
	 */
	public function render_core_tab( string $active_tab, string $active_subtab ): void {
		unset( $active_tab, $active_subtab );
		echo '<div id="kjeks-network-root"></div>';
	}

	/**
	 * Render the Settings submenu page (flags + scanner key).
	 */
	public function render_settings(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}

		echo '<div class="wrap kjeks-settings"><h1>' . esc_html__( 'Cookie Consent Settings', 'kjeks' ) . '</h1><div id="kjeks-network-root"></div></div>';
	}

	public function enqueue( string $hook ): void {
		$is_shell    = ( 'toplevel_page_' . self::SLUG === $hook );
		$is_settings = ( '' !== $this->settings_hook && $this->settings_hook === $hook );

		if ( $is_shell || $is_settings ) {
			$this->enqueue_shell_style();
		}

		if ( $is_shell ) {
			$active_tab    = $this->active_tab();
			$active_subtab = $this->active_subtab();

			if ( in_array( $active_tab, array( 'cookies', 'banner' ), true ) ) {
				$this->enqueue_core_app( $active_tab );
			}

			/**
			 * Fires while enqueuing assets for the Cookie Consent screen.
			 *
			 * Add-ons enqueue their own bundle here, but only when their tab is
			 * the active one.
			 *
			 * @since 1.3.0
			 *
			 * @param string $active_tab    Active tab slug.
			 * @param string $active_subtab Active subtab slug.
			 */
			do_action( 'kjeks_settings_enqueue_scripts', $active_tab, $active_subtab );
			return;
		}

		if ( '' !== $this->settings_hook && $this->settings_hook === $hook ) {
			$this->enqueue_core_app( 'settings' );
		}
	}

	/**
	 * Enqueue the shared shell stylesheet so every tab — the React views and
	 * the server-rendered add-on form tabs — share one card-based look.
	 */
	private function enqueue_shell_style(): void {
		wp_register_style( 'kjeks-settings-shell', false, array(), KJEKS_VERSION );
		wp_enqueue_style( 'kjeks-settings-shell' );

		$css = <<<'CSS'
.kjeks-settings .kjeks-tab-content { margin-top: 20px; }
.kjeks-card {
	box-sizing: border-box;
	max-width: 780px;
	margin: 0 0 20px;
	padding: 8px 24px 24px;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.04 );
}
.kjeks-card .form-table { margin-top: 0; }
.kjeks-card .form-table th { font-weight: 600; }
.kjeks-card--intro { padding: 16px 24px; }
.kjeks-card--intro h2 { margin: 0 0 4px; font-size: 15px; }
.kjeks-card--intro p { margin: 0; color: #50575e; }
.kjeks-card .form-table > tbody > tr > th,
.kjeks-card .form-table > tbody > tr > td { padding: 16px 10px; }
.kjeks-card .form-table td .widefat { border: 0; box-shadow: none; }
.kjeks-card .form-table td select { min-width: 12em; }
.kjeks-card p.submit { margin: 12px 0 0; padding: 0; }
CSS;

		wp_add_inline_style( 'kjeks-settings-shell', $css );
	}

	/**
	 * Enqueue the core review bundle for a given view.
	 *
	 * @param string $view One of 'cookies', 'banner', 'settings'.
	 */
	private function enqueue_core_app( string $view ): void {
		$asset_file = KJEKS_DIR . 'build/network.asset.php';
		$asset      = is_readable( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => KJEKS_VERSION,
			);

		wp_enqueue_script(
			'kjeks-network',
			KJEKS_URL . 'build/network.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( KJEKS_DIR . 'build/network.css' ) ) {
			wp_enqueue_style( 'kjeks-network', KJEKS_URL . 'build/network.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'kjeks-network', 'kjeks', KJEKS_DIR . 'languages' );

		wp_localize_script(
			'kjeks-network',
			'kjeksNetwork',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'kjeks/v1/network-config' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isMultisite' => is_multisite(),
				'view'        => $view,
			)
		);
	}

	/**
	 * Active tab slug from the query string.
	 */
	private function active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector for display; sanitized, no state change.
		return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'cookies';
	}

	/**
	 * Active subtab slug from the query string.
	 */
	private function active_subtab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only subtab selector for display; sanitized, no state change.
		return isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : '';
	}

	/**
	 * Base admin URL for a Cookie Consent page, install-type aware.
	 *
	 * @param string $slug Page slug.
	 */
	private function page_url( string $slug ): string {
		$path = 'admin.php?page=' . $slug;

		return is_multisite() ? network_admin_url( $path ) : admin_url( $path );
	}
}
