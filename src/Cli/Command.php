<?php
/**
 * WP-CLI commands.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Cli;

use Soderlind\Kjeks\Scan\ScanConfig;
use Soderlind\Kjeks\Scan\ScanImporter;
use Soderlind\Kjeks\Scan\ScanValidator;
use WP_CLI;

/**
 * Manages Kjeks from the command line.
 */
final class Command {

	/**
	 * Imports a scan JSON file as unreviewed observations.
	 *
	 * Accepts either a single-site payload (`{ blog_id, observations }`) or a
	 * scanner output file (`{ sites: [ { blog_id, observations }, ... ] }`).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a scan JSON file produced by the scanner.
	 *
	 * [--blog_id=<id>]
	 * : Target blog id for a single-site payload. Ignored for scanner files.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks import scan/example.com.json
	 *     wp kjeks import scan/site2.json --blog_id=3
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read file: {$file}" );
		}

		// Reading a local CLI-supplied file, not a remote resource.
		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $decoded ) ) {
			WP_CLI::error( 'File is not valid JSON.' );
		}

		$payloads = array();
		if ( isset( $decoded['sites'] ) && is_array( $decoded['sites'] ) ) {
			foreach ( $decoded['sites'] as $site ) {
				if ( is_array( $site ) ) {
					$payloads[] = $site;
				}
			}
		} else {
			if ( isset( $assoc_args['blog_id'] ) ) {
				$decoded['blog_id'] = (int) $assoc_args['blog_id'];
			}
			$payloads[] = $decoded;
		}

		if ( array() === $payloads ) {
			WP_CLI::error( 'No site payloads found in file.' );
		}

		$validator = new ScanValidator();
		$importer  = new ScanImporter();
		$total     = 0;

		foreach ( $payloads as $payload ) {
			$result = $validator->validate( $payload );

			foreach ( $result['warnings'] as $warning ) {
				WP_CLI::warning( $warning );
			}

			if ( ! $result['valid'] ) {
				WP_CLI::warning( 'Skipping invalid site: ' . implode( ' ', $result['errors'] ) );
				continue;
			}

			$imported = $importer->import( $result['blog_id'], $result['trackers'] );
			$total   += $imported;
			WP_CLI::log( sprintf( 'Site %d: imported %d unreviewed observation(s).', $result['blog_id'], $imported ) );
		}

		WP_CLI::success(
			sprintf(
				'Imported %d unreviewed observation(s). Review them under Settings → Cookie Consent.',
				$total
			)
		);
	}

	/**
	 * Generates a scanner config from the network's sites.
	 *
	 * Writes the JSON the discovery scanner consumes (one entry per site, with
	 * url, blog_id, policy_version, and paths). Prints to STDOUT by default so
	 * it can be redirected into scanner/config.json.
	 *
	 * ## OPTIONS
	 *
	 * [--paths=<paths>]
	 * : Comma-separated representative paths for every site. Default: /
	 *
	 * [--output=<file>]
	 * : Write to this file instead of STDOUT.
	 *
	 * [--include=<ids>]
	 * : Comma-separated blog ids to include. Default: all public, non-archived,
	 *   non-deleted sites.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks scan-config > scanner/config.json
	 *     wp kjeks scan-config --paths=/,/about,/contact --output=scanner/config.json
	 *     wp kjeks scan-config --include=1,3
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function scan_config( array $args, array $assoc_args ): void {
		$paths = isset( $assoc_args['paths'] )
			? array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['paths'] ) ) ) )
			: array( '/' );

		$include = isset( $assoc_args['include'] )
			? array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['include'] ) ) ) )
			: array();

		$config = ( new ScanConfig() )->build( $include, $paths );

		if ( array() === $config['sites'] ) {
			WP_CLI::error( 'No matching sites found.' );
		}

		$json = (string) wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( isset( $assoc_args['output'] ) ) {
			$file = (string) $assoc_args['output'];
			// Writing a CLI-requested local file.
			if ( false === file_put_contents( $file, $json . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				WP_CLI::error( "Could not write to {$file}" );
			}
			WP_CLI::success( sprintf( 'Wrote %d site(s) to %s', count( $config['sites'] ), $file ) );
			return;
		}

		WP_CLI::line( $json );
	}
}
