<?php
/**
 * Plugin bootstrap.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks;

use Soderlind\Kjeks\Admin\NetworkAdmin;
use Soderlind\Kjeks\Admin\SiteSettings;
use Soderlind\Kjeks\Blocking\ScriptGate;
use Soderlind\Kjeks\Cli\Command;
use Soderlind\Kjeks\Frontend\Banner;
use Soderlind\Kjeks\Lifecycle\SiteInitializer;
use Soderlind\Kjeks\Rest\ImportController;
use Soderlind\Kjeks\Rest\NetworkConfigController;
use Soderlind\Kjeks\Rest\ScanConfigController;
use Soderlind\Kjeks\Rest\SettingsController;

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
		( new SettingsController() )->hooks();
		( new ImportController() )->hooks();
		( new ScanConfigController() )->hooks();
		( new NetworkConfigController() )->hooks();
		( new ScriptGate() )->hooks();
		( new Banner() )->hooks();

		if ( is_admin() ) {
			( new SiteSettings() )->hooks();
			( new NetworkAdmin() )->hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'kjeks import', array( Command::class, 'import' ) );
			\WP_CLI::add_command( 'kjeks scan-config', array( Command::class, 'scan_config' ) );
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
