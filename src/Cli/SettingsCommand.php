<?php
/**
 * WP-CLI commands for config bundle export / apply.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Cli;

use RuntimeException;
use Soderlind\Kjeks\Config\ConfigBundle;
use WP_CLI;

/**
 * Exports and applies portable Kjeks config bundles.
 */
final class SettingsCommand {

	/**
	 * Exports this install's authored settings as a Config bundle (JSON).
	 *
	 * Prints to STDOUT so it can be redirected to a file. The bundle carries
	 * authored settings only — never scan observations, secrets, or visitor
	 * consent.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks settings export > kjeks-config.json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function export( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$bundle = ( new ConfigBundle() )->export();
		$json   = (string) wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		WP_CLI::line( $json );
	}

	/**
	 * Applies a Config bundle to this install.
	 *
	 * Replaces each authored option with the bundle's value; the tracker
	 * registry merges by identity so local review work is preserved. Bumps the
	 * policy version, so visitors are re-prompted.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a Config bundle JSON file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks settings apply kjeks-config.json
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function apply( array $args, array $assoc_args ): void {
		unset( $assoc_args );

		$file = $args[0] ?? '';
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read file: {$file}" );
		}

		// Reading a local CLI-supplied file, not a remote resource.
		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI-supplied path validated with is_readable(); not remote, WP_Filesystem not warranted for a read.
		if ( ! is_array( $decoded ) ) {
			WP_CLI::error( 'File is not valid JSON.' );
		}

		try {
			$result = ( new ConfigBundle() )->apply( $decoded );
		} catch ( RuntimeException $e ) {
			WP_CLI::error( $e->getMessage() );
			return;
		}

		foreach ( $result['warnings'] as $warning ) {
			WP_CLI::warning( $warning );
		}

		if ( array() !== $result['skipped'] ) {
			WP_CLI::log( 'Skipped section(s): ' . implode( ', ', $result['skipped'] ) );
		}

		WP_CLI::success(
			sprintf(
				'Applied %d section(s). Policy version bumped to %d; visitors will be re-prompted.',
				count( $result['applied'] ),
				$result['policy_version']
			)
		);
	}
}
