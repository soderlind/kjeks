<?php
/**
 * Minimal WP-CLI stub for PHPStan.
 *
 * WP_CLI is not part of php-stubs/wordpress-stubs, and the official
 * php-stubs/wp-cli-stubs package pulls in dependencies that conflict with this
 * project's toolchain. This declares only the surface the plugin calls, and is
 * used solely for static analysis (phpstan `scanFiles`); it is never loaded at
 * runtime.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

class WP_CLI {

	/**
	 * @param string                                                    $name
	 * @param string|callable|object|array{0: class-string|object, 1: string} $callable
	 * @param array<string, mixed>                                      $args
	 */
	public static function add_command( $name, $callable, $args = array() ): bool {
	}

	/**
	 * @param string|\WP_Error $message
	 * @param bool|int         $exit
	 */
	public static function error( $message, $exit = true ): void {
	}

	public static function warning( string $message ): void {
	}

	public static function success( string $message ): void {
	}

	public static function log( string $message ): void {
	}

	public static function line( string $message = '' ): void {
	}
}
