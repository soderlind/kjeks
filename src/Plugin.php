<?php
/**
 * Plugin bootstrap.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks;

use Soderlind\Kjeks\Admin\NetworkAdmin;
use Soderlind\Kjeks\Blocking\ScriptGate;
use Soderlind\Kjeks\Cli\Command;
use Soderlind\Kjeks\Frontend\Banner;
use Soderlind\Kjeks\Integration\RestrictedSiteAccess;
use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Lifecycle\SiteInitializer;
use Soderlind\Kjeks\Privacy\PolicyContent;
use Soderlind\Kjeks\Privacy\PrivacyPageDeclaration;
use Soderlind\Kjeks\Rest\ImportController;
use Soderlind\Kjeks\Rest\NetworkConfigController;
use Soderlind\Kjeks\Rest\ScanConfigController;

/**
 * Wires the plugin's subsystems.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		( new SiteInitializer() )->hooks();
		( new ImportController() )->hooks();
		( new ScanConfigController() )->hooks();
		( new NetworkConfigController() )->hooks();
		( new ScriptGate() )->hooks();
		( new Banner() )->hooks();
		( new RestrictedSiteAccess() )->hooks();
		( new PrivacyPageDeclaration( new NetworkStore() ) )->hooks();

		if ( is_admin() ) {
			( new NetworkAdmin() )->hooks();
			( new PolicyContent() )->hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'kjeks import', array( Command::class, 'import' ) );
			\WP_CLI::add_command( 'kjeks scan-config', array( Command::class, 'scan_config' ) );
			\WP_CLI::add_command( 'kjeks scan-key', array( Command::class, 'scan_key' ) );
		}

		add_action(
			'init',
			static function (): void {
				/**
				 * Fires so integrations can register themselves.
				 *
				 * Use `kjeks_register_integration()` inside this action.
				 */
				do_action( 'kjeks_register_integrations' );
			},
			20
		);
	}
}
