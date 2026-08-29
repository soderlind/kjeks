<?php
/**
 * Selects representative URLs to scan for a single site.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Scan;

/**
 * Picks the paths the discovery scanner should visit for the current blog.
 *
 * Strategy (call inside a switch_to_blog context):
 *  - Template set: home, newest post, newest page, the posts archive. Catches
 *    site-wide technologies that load in the header/footer of every page.
 *  - Embed-flagged content: published entries whose content shows an embed/script
 *    signal. Catches page-specific third parties (YouTube, maps, social widgets).
 *  - Fill: newest published entries, up to the cap, so new pages still get seen.
 *
 * All candidate URLs are reduced to paths relative to the site home and de-duped,
 * then truncated to the cap. Pure helpers are static so they can be unit-tested
 * without WordPress.
 */
final class PageSampler {

	public function __construct( private int $cap = 10 ) {
		if ( $this->cap < 1 ) {
			$this->cap = 1;
		}
	}

	/**
	 * Representative paths (relative to the site home) for the current blog.
	 *
	 * @return array<int, string>
	 */
	public function paths(): array {
		$home = (string) home_url( '/' );

		// Template set — always first so it survives the cap.
		$urls = array( $home );
		foreach ( array( 'post', 'page' ) as $type ) {
			$permalink = $this->newest_permalink( $type );
			if ( '' !== $permalink ) {
				$urls[] = $permalink;
			}
		}
		$archive = $this->archive_url();
		if ( '' !== $archive ) {
			$urls[] = $archive;
		}

		// One recent batch drives both embed-flagging and the fill.
		$candidates = $this->recent_candidates( 100 );

		foreach ( $candidates as $candidate ) {
			if ( self::has_embed_signal( $candidate['content'] ) ) {
				$urls[] = $candidate['url'];
			}
		}
		foreach ( $candidates as $candidate ) {
			$urls[] = $candidate['url'];
		}

		return $this->finalize( $home, $urls );
	}

	/**
	 * Whether post content shows a third-party embed or inline-script signal.
	 *
	 * Cheap server-side heuristic — the scanner still confirms what actually loads.
	 */
	public static function has_embed_signal( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}

		$markers = array( '<iframe', '<script', '[embed', 'wp:embed', 'wp:core-embed', 'wp-block-embed', '<blockquote class="twitter', '<blockquote class="instagram' );
		foreach ( $markers as $marker ) {
			if ( false !== stripos( $content, $marker ) ) {
				return true;
			}
		}

		// Bare oEmbed provider URLs (classic-editor auto-embeds).
		$hosts = array( 'youtube.com', 'youtu.be', 'vimeo.com', 'twitter.com', 'tiktok.com', 'instagram.com', 'facebook.com', 'spotify.com', 'soundcloud.com', 'google.com/maps' );
		foreach ( $hosts as $host ) {
			if ( false !== stripos( $content, $host ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reduces an absolute URL to a path relative to the site home.
	 *
	 * @return string Path with a leading slash (for example `/about/`).
	 */
	public static function relative_path( string $home, string $url ): string {
		$home_path = (string) wp_parse_url( $home, PHP_URL_PATH );
		$url_path  = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( '' === $url_path ) {
			$url_path = '/';
		}
		if ( '' !== $home_path && '/' !== $home_path && str_starts_with( $url_path, $home_path ) ) {
			$url_path = substr( $url_path, strlen( $home_path ) );
		}

		return '/' . ltrim( $url_path, '/' );
	}

	/**
	 * @param array<int, string> $urls
	 *
	 * @return array<int, string>
	 */
	private function finalize( string $home, array $urls ): array {
		$paths = array();
		foreach ( $urls as $url ) {
			$path = self::relative_path( $home, (string) $url );
			if ( ! in_array( $path, $paths, true ) ) {
				$paths[] = $path;
			}
			if ( count( $paths ) >= $this->cap ) {
				break;
			}
		}

		return array() === $paths ? array( '/' ) : $paths;
	}

	private function newest_permalink( string $type ): string {
		$ids = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		if ( array() === (array) $ids ) {
			return '';
		}

		$url = get_permalink( (int) $ids[0] );

		return is_string( $url ) ? $url : '';
	}

	private function archive_url(): string {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			$url = get_permalink( $posts_page );
			if ( is_string( $url ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * @return array<int, array{url: string, content: string}>
	 */
	private function recent_candidates( int $limit ): array {
		$posts = get_posts(
			array(
				'post_type'        => $this->public_types(),
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$candidates = array();
		foreach ( (array) $posts as $post ) {
			$url = get_permalink( $post );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$candidates[] = array(
				'url'     => $url,
				'content' => $post->post_content,
			);
		}

		return $candidates;
	}

	/**
	 * @return array<int, string>
	 */
	private function public_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		return array_values( $types );
	}
}
