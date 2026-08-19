<?php
/**
 * Resolves effective banner display content.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Frontend;

use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Inventory\SiteStore;

/**
 * Merges network default content with the current site's overrides.
 */
final class ContentResolver {

	public function __construct(
		private readonly NetworkStore $network,
		private readonly SiteStore $site,
	) {}

	/**
	 * @return array<string, string>
	 */
	public function resolve(): array {
		$content = array_merge( $this->network->content(), $this->site->content_overrides() );

		/**
		 * Filters the resolved banner content.
		 *
		 * Translation plugins can localise copy here.
		 *
		 * @param array<string, string> $content Content values.
		 * @param int                   $blog_id Current blog id.
		 */
		return (array) apply_filters( 'kjeks_banner_content', $content, $this->site->blog_id() );
	}
}
