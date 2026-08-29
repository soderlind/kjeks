<?php
/**
 * Plugin Name:       Kjeks
 * Plugin URI:        https://github.com/soderlind/kjeks
 * Description:       Cookie consent management for WordPress (single site or Multisite): per-site tracker inventories, prior blocking of non-essential technologies, and an accessible consent banner. Assists with consent management; does not claim automatic legal compliance.
 * Version:           1.1.4
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Author:            Per Søderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kjeks
 * Domain Path:       /languages
 * Network:           true
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KJEKS_VERSION', '1.1.4' );
define( 'KJEKS_FILE', __FILE__ );
define( 'KJEKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KJEKS_URL', plugin_dir_url( __FILE__ ) );
define( 'KJEKS_BASENAME', plugin_basename( __FILE__ ) );

$kjeks_autoload = KJEKS_DIR . 'vendor/autoload.php';
if ( is_readable( $kjeks_autoload ) ) {
	require $kjeks_autoload;
}

register_activation_hook( __FILE__, array( Lifecycle\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Lifecycle\Activation::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		// Translations load just-in-time on WordPress 6.8+ (bundled languages/ and wp.org).
		Plugin::instance()->boot();
	}
);
