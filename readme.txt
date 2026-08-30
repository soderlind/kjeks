=== Kjeks ===
Contributors: PerS
Tags: cookies, consent, gdpr, privacy, multisite
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.1.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent for WordPress single-site and Multisite: tracker inventory, prior blocking of non-essential tech, and an accessible consent banner.

== Description ==

Kjeks assists with cookie and tracker consent management on WordPress — single-site or Multisite. It provides:

* A central place to review and classify every tracker once — site-wide on a single install, or network-wide on Multisite (with per-site inventories).
* Prior blocking of non-essential scripts, inline snippets, pixels, and embeds until the visitor consents.
* An accessible, translatable consent banner with equally prominent Accept all / Reject all / Customize.
* A developer API to register integrations against consent categories.

Kjeks assists with consent management. It does not, and cannot, guarantee legal compliance. Discovery is observational and cannot prove the absence of tracking. Consider technologies beyond cookies.

Full documentation, guides, and the developer handbook: [kjeks.soderlind.no](https://kjeks.soderlind.no)

= Free add-ons =

Optional companion plugins extend Kjeks:

* **Kjeks Google** — Google Tag Manager and GA4 using Consent Mode v2, with default-denied signals and a consent-gated container. [Docs](https://kjeks.soderlind.no/docs/kjeks-google/) · [Code](https://github.com/soderlind/kjeks-google)
* **Kjeks AI Reviewer** — AI-assisted, advisory classification suggestions for unreviewed cookies, using the WordPress core AI client. [Docs](https://kjeks.soderlind.no/docs/kjeks-ai-reviewer/) · [Code](https://github.com/soderlind/kjeks-ai-reviewer)

= Discovery scanner =

**Kjeks Scanner** is a standalone Node + Playwright command-line tool (not a WordPress plugin) that crawls your pages under every consent state, detects the trackers that actually fire, and imports them into the inventory. [Docs](https://kjeks.soderlind.no/docs/kjeks-scanner/) · [Code](https://github.com/soderlind/kjeks-scanner)

== Installation ==

1. Install Kjeks from **Plugins → Add New**, or upload the plugin zip via **Plugins → Add New → Upload Plugin**.
2. On a single site, activate the plugin and open **Cookie Consent** from the admin menu. On Multisite, **Network Activate** it and configure defaults under **Network Admin → Cookie Consent**.
3. Set the banner heading, body text, privacy-policy link, and accent colour, then review your tracker inventory.

== Frequently Asked Questions ==

= Why isn't the consent banner showing? =

Usually expected behaviour, not a bug. Kjeks skips the banner when:

* A choice is already stored. Consent lasts about six months (the kjeks_consent cookie and localStorage); returning visitors see the "Cookie settings" trigger instead. Clear site data or use a private window to see the banner again.
* The browser sends Global Privacy Control (GPC). Brave sends GPC by default (also DuckDuckGo, Firefox's "Tell websites not to sell or share my data", and some extensions). Kjeks honours GPC by default, so it auto-applies reject-non-essential and shows only the trigger. Opt out with the kjeks_honor_gpc filter.
* A browser or extension hides cookie notices, such as Brave Shields' "block cookie consent notices" or uBlock Origin's cookie-notice lists. Kjeks cannot override that.
* The default banner is turned off (under Cookie Consent, or Network Admin → Cookie Consent on Multisite), so only the trigger shows.
* The banner script or storage (cookies/localStorage) is blocked.

== Changelog ==

= 1.1.5 =
* Add a portable Config bundle (export / apply): migrate an install's authored settings — banner content, network settings, and each add-on's options via the `kjeks_config_sections` filter — to another install. Available as `wp kjeks settings export|apply` and an Import / Export screen under Cookie Consent.
* Ship the tracker registry as a normalized authored slice (manual + reviewed only, keyed by identity); applying merges by identity so local review work is preserved. Applying a bundle bumps the policy version to re-prompt visitors.
* Add a Playwright acceptance-test suite covering the core banner, gating, and add-ons (dev-only; not shipped in the plugin zip).

= 1.1.4 =
* Add the `Soderlind\Kjeks\AddonKit` base classes (`Categories`, `Options`, `SettingsPage`, `Plugin`) so add-ons can share the consent-category picker, network/site option storage, and settings-screen scaffolding.
* Mount add-on settings screens as submenus of the core "Cookie Consent" menu, registered after the parent so the pages resolve correctly on both single-site and Multisite.
* Load the public API file with `return` instead of `exit` when accessed outside WordPress, so Composer-based tooling (PHPCS/PHPStan) analyses the plugin instead of exiting during autoload.

= 1.1.3 =
* Correct the internal version used for asset cache busting and document the safety rationale for WordPress.org review exceptions.

= 1.1.2 =
* Confine `wp kjeks scan-config --output` to the uploads `kjeks/` directory and write via `WP_Filesystem`, preventing writes to arbitrary locations.
* Escape inline gated-script output to prevent a `</script>` breakout in the served markup.
* Ship only the `.pot` template in the distributed zip; compiled translations now come from translate.wordpress.org.
* Correct the readme Contributors username to the WordPress.org plugin owner.
* Documentation: cover single-site and Multisite, and clarify that the scanner is a standalone tool.

= 1.1.1 =
* Fully remove the GitHub self-updater (drop the init and the composer dependency); WordPress.org is now the sole update source.
* Build the GitHub release zip identically to the WordPress.org distribution — same files, no self-updater, `composer.json` included.

= 1.1.0 =
* Prepare the plugin for the WordPress.org plugin directory: add a `.distignore` and WordPress.org deploy + asset-update GitHub Actions.
* Pass Plugin Check: add a direct-file-access guard and rely on WordPress 6.8+ just-in-time translation loading (drop the manual `load_plugin_textdomain()` call).
* Exclude the GitHub self-updater from the WordPress.org build; GitHub-hosted installs kept the updater.
* Link the readme to the [documentation site](https://kjeks.soderlind.no) and the Kjeks add-ons.

= 1.0.8 =
* Harden the Restricted Site Access bypass: send no-cache headers on a keyed request so an unrestricted response can never be cached and served to the public, and accept the key only from the X-Kjeks-Key header (no query-string fallback) so it can't leak via logs, Referer, or history.

= 1.0.7 =
* Restricted Site Access compatibility: a request carrying a valid scanner key bypasses the login/IP restriction, so the discovery scanner can reach the scan-config and import endpoints and load restricted front-end pages. Requests without a valid key stay restricted.

= 1.0.6 =
* Manage the scanner key from Network Admin → Cookie Consent → Settings: view, copy, regenerate, or clear it — no WP-CLI required.

= 1.0.5 =
* Add a shared scanner key for the scan-config and import REST endpoints: send it in the X-Kjeks-Key header (or scan_key query argument) so CI authenticates even behind proxies that strip the Authorization header. Manage it with `wp kjeks scan-key`. Application-password / capability auth still works as a fallback.

= 1.0.4 =
* Track the compiled `build/` assets in version control so Composer source installs (e.g. `~1.0.3` from a VCS/registry) ship the admin and banner scripts, fixing a 404 on `build/network.js` after activation.

= 1.0.3 =
* Network review: replace the truncated site-count tooltip with a scrollable, numerically sorted popover that closes on Escape or click outside, so all sites are reachable.
* Network review: allow assigning the Necessary category when reviewing cookies.

= 1.0.2 =
* Add suggested cookie and consent text to the WordPress Privacy Policy Guide (Settings → Privacy).
* Optionally append the live cookie declaration to the site's privacy policy page — a network opt-in (off by default) with a dedup guard so it isn't duplicated when the Cookie declaration block or [kjeks_cookie_declaration] shortcode is already on the page.
* Add a "Show the cookie declaration on the privacy policy page" toggle to the network admin Settings tab.

= 1.0.1 =
* Register two block-editor blocks via block.json metadata: "Cookie settings" (kjeks/preferences) and "Cookie declaration" (kjeks/cookie-declaration), and add an editor script so both appear in the inserter.
* Register a shared kjeks-banner stylesheet so the blocks' style handle resolves on the front end and in the editor's ServerSideRender preview.
* Reorganize the network admin into Cookies, Banner, and Settings tabs.
* Add JavaScript translations for the block editor.

= 1.0.0 =
* First stable release.
* Add a Norwegian Bokmål (nb_NO) translation and refresh the translation template (.pot).
* Add JavaScript (admin UI) translations via wp i18n make-json, wired with wp_set_script_translations and an i18n-map.json.
* Document why the consent banner may not appear (Global Privacy Control, a prior choice, or browser blockers).

= 0.9.0 =
* Scanner config now auto-selects representative URLs per site (home, newest post and page, the posts archive, and pages whose content shows an embed or inline-script signal), capped and filterable. Omit paths to auto-select; explicit paths still override.
* Add a cap REST parameter and --cap CLI option, plus kjeks_scan_url_cap and kjeks_scan_paths filters.

= 0.8.0 =
* Self-updates from GitHub releases via the wordpress-github-updater library (bundled plugin-update-checker). Define the optional KJEKS_GITHUB_TOKEN constant for private repositories or higher GitHub API rate limits.
* Add GitHub Actions workflows to build and attach the release ZIP on published releases and on manual dispatch.

= 0.7.0 =
* Add single-site support: the consent admin registers under the normal admin menu (manage_options) on non-multisite installs; the review UI drops the "network" wording and hides the Sites column.

= 0.6.0 =
* Breaking: remove the per-site consent admin screen and the /kjeks/v1/site-config REST route; all administration is now under Network Admin.
* Default the banner privacy link to each site's core privacy page (get_privacy_policy_url()); add the kjeks_privacy_url filter.
* Drop per-site local trackers and content overrides; legacy options are cleaned up on uninstall.

= 0.5.0 =
* Add a network-admin toggle to show the consent banner until a visitor makes a choice (default on).
* Hide the redundant "Cookie settings" trigger while the banner is visible.
* Honor programmatically injected consent records whose policy version / blog id are numeric, fixing consent-state scanning by the discovery scanner.

= 0.4.0 =
* Move the discovery scanner into its own repository (soderlind/kjeks-scanner); it integrates over the unchanged REST scan-config / import contract. No plugin runtime change.
* Document the plugin family: add an ecosystem overview (kjeks, kjeks-google, kjeks-ai-reviewer, kjeks-scanner) with integration seams to docs/architecture.md.

= 0.3.0 =
* Add a network-admin tab extension seam so add-ons can contribute their own tabs to the Kjeks network screen via the kjeks.networkAdminTabs JavaScript filter.
* Fix the KJEKS_VERSION constant, which was left at 0.1.0.

= 0.2.0 =
* Split the network tracker registry into its own module, separate from network settings.
* Resolve the effective inventory through a single module; remove the unused per-site override path.
* Add a Tracker identity rule so the same cookie aggregates consistently across sites.

= 0.1.0 =
* Initial Phase 1 release: multisite runtime, blocking API, admin registry, adapters, tests, docs.
