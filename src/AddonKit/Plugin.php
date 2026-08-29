<?php
/**
 * Add-on kit: singleton plugin bootstrap.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

/**
 * Minimal singleton base for a Kjeks add-on's main plugin object.
 *
 * Extend it and implement `boot()` to wire the add-on's subsystems. The
 * `boot()` call is guarded so repeated `instance()->boot()` is a no-op.
 *
 * Public, versioned API.
 *
 * @since 1.2.0
 */
abstract class Plugin {

	/**
	 * @var array<class-string, static>
	 */
	private static array $instances = array();

	private bool $booted = false;

	final public static function instance(): static {
		if ( ! isset( self::$instances[ static::class ] ) ) {
			self::$instances[ static::class ] = new static();
		}

		return self::$instances[ static::class ];
	}

	final public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register();
	}

	/**
	 * Wire the add-on's hooks and subsystems. Runs once.
	 */
	abstract protected function register(): void;

	protected function __construct() {}
}
