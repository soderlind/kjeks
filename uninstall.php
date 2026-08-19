<?php
/**
 * Uninstall handler.
 *
 * Never deletes site data unless the network-level opt-in is enabled.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kjeks_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $kjeks_autoload ) ) {
	require $kjeks_autoload;
}

if ( class_exists( \Soderlind\Kjeks\Lifecycle\Uninstall::class ) ) {
	\Soderlind\Kjeks\Lifecycle\Uninstall::run();
}
