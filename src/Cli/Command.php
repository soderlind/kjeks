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
use Soderlind\Kjeks\Scan\ScanKeyAuth;
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
		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI-supplied file path already validated with is_readable(); not a remote resource, WP_Filesystem not warranted for a read.
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
	 * : Comma-separated representative paths for every site. Omit to auto-select
	 *   representative URLs per site (home, newest post/page, embed-bearing pages).
	 *
	 * [--cap=<n>]
	 * : Max auto-selected paths per site. Default: 10. Ignored when --paths is set.
	 *
	 * [--output=<file>]
	 * : Write to a file inside the uploads `kjeks/` directory. Only a file name is
	 *   honoured; any path components are stripped. Use shell redirection for
	 *   arbitrary destinations.
	 *
	 * [--include=<ids>]
	 * : Comma-separated blog ids to include. Default: all public, non-archived,
	 *   non-deleted sites.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks scan-config > scanner/config.json
	 *     wp kjeks scan-config --paths=/,/about,/contact --output=config.json
	 *     wp kjeks scan-config --cap=15 --include=1,3
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function scan_config( array $args, array $assoc_args ): void {
		$paths = isset( $assoc_args['paths'] )
			? array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['paths'] ) ) ) )
			: null;

		$cap = isset( $assoc_args['cap'] ) ? max( 1, (int) $assoc_args['cap'] ) : 10;

		$include = isset( $assoc_args['include'] )
			? array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['include'] ) ) ) )
			: array();

		$config = ( new ScanConfig() )->build( $include, $paths, $cap );

		if ( array() === $config['sites'] ) {
			WP_CLI::error( 'No matching sites found.' );
		}

		$json = (string) wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( isset( $assoc_args['output'] ) ) {
			$file = $this->resolve_output_path( (string) $assoc_args['output'] );

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			if ( ! $wp_filesystem->put_contents( $file, $json . "\n", FS_CHMOD_FILE ) ) {
				WP_CLI::error( "Could not write to {$file}" );
			}
			WP_CLI::success( sprintf( 'Wrote %d site(s) to %s', count( $config['sites'] ), $file ) );
			return;
		}

		WP_CLI::line( $json );
	}

	/**
	 * Resolves a CLI --output value to a safe path inside the uploads kjeks/ dir.
	 *
	 * Only the sanitized file name is honoured; directory components (including
	 * traversal sequences) are discarded so writes cannot escape the directory.
	 */
	private function resolve_output_path( string $output ): string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			WP_CLI::error( (string) $uploads['error'] );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'kjeks';
		if ( ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( "Could not create directory: {$dir}" );
		}

		$name = sanitize_file_name( basename( $output ) );
		if ( '' === $name ) {
			$name = 'scan-config.json';
		}

		return trailingslashit( $dir ) . $name;
	}

	/**
	 * Manages the shared scanner authentication key.
	 *
	 * The scanner presents this key in the `X-Kjeks-Key` header (or `scan_key`
	 * query argument) to authenticate against the scan-config and import
	 * endpoints without a WordPress application password — useful when a proxy
	 * strips the Authorization header. Store the generated value as the
	 * `KJEKS_SCAN_KEY` secret in CI.
	 *
	 * ## OPTIONS
	 *
	 * [--generate]
	 * : Generate and store a new key, replacing any existing one, then print it.
	 *
	 * [--clear]
	 * : Remove the stored key, disabling key-based scanner auth.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kjeks scan-key                 # print the current key
	 *     wp kjeks scan-key --generate      # rotate the key and print it
	 *     wp kjeks scan-key --clear
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function scan_key( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['clear'] ) ) {
			ScanKeyAuth::clear();
			WP_CLI::success( 'Scanner key cleared. Key-based scanner auth is now disabled.' );
			return;
		}

		if ( isset( $assoc_args['generate'] ) ) {
			$key = ScanKeyAuth::generate();
			WP_CLI::line( $key );
			WP_CLI::success( 'Generated a new scanner key. Store it as the KJEKS_SCAN_KEY secret in CI.' );
			return;
		}

		$key = ScanKeyAuth::stored_key();
		if ( '' === $key ) {
			WP_CLI::warning( 'No scanner key set. Run "wp kjeks scan-key --generate" to create one.' );
			return;
		}

		WP_CLI::line( $key );
	}
}
