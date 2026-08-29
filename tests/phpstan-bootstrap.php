<?php
/**
 * PHPStan bootstrap: define the plugin constants that src/ references.
 *
 * kjeks.php cannot be loaded outside WordPress (it exits and calls core
 * functions at file scope), so this stub supplies the constant values for
 * static analysis instead.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

define( 'KJEKS_VERSION', '1.1.4' );
define( 'KJEKS_FILE', dirname( __DIR__ ) . '/kjeks.php' );
define( 'KJEKS_DIR', dirname( __DIR__ ) . '/' );
define( 'KJEKS_URL', 'https://example.com/wp-content/plugins/kjeks/' );
define( 'KJEKS_BASENAME', 'kjeks/kjeks.php' );
