<?php
/**
 * Resolves effective banner display content.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Frontend;

use Soderlind\Kjeks\Inventory\NetworkStore;

/**
 * Resolves the banner display content from the network defaults.
 *
 * Per-site copy is intentionally not stored: the network content is the single
 * source, and the per-site privacy policy link falls back to WordPress core's
 * own privacy page. Both the privacy URL and the whole content array remain
 * filterable for programmatic per-site or per-locale overrides.
 */
final class ContentResolver {

	public function __construct(
		private readonly NetworkStore $network,
	) {}

	/**
	 * @return array<string, string>
	 */
	public function resolve(): array {
		$content = $this->network->content();

		// Default the banner's privacy link to this site's own core privacy page.
		if ( '' === (string) ( $content['privacy_url'] ?? '' ) ) {
			$content['privacy_url'] = (string) get_privacy_policy_url();
		}

		/**
		 * Filters the resolved privacy policy URL used in the banner.
		 *
		 * @param string $privacy_url Resolved URL.
		 * @param int    $blog_id     Current blog id.
		 */
		$content['privacy_url'] = (string) apply_filters( 'kjeks_privacy_url', $content['privacy_url'], get_current_blog_id() );

		/**
		 * Filters the resolved banner content.
		 *
		 * Translation plugins can localise copy here.
		 *
		 * @param array<string, string> $content Content values.
		 * @param int                   $blog_id Current blog id.
		 */
		return (array) apply_filters( 'kjeks_banner_content', $content, get_current_blog_id() );
	}
}
