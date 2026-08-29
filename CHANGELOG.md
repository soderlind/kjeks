# Changelog

## Unreleased

- Add the `Soderlind\Kjeks\AddonKit` base classes (`Categories`, `Options`, `SettingsPage`, `Plugin`) so add-ons can share the consent-category picker, network/site option storage, and settings-screen scaffolding instead of re-implementing them.

## 1.1.3 - 2026-08-27

- Correct the internal version used for asset cache busting and document the safety rationale for WordPress.org review exceptions.

## 1.1.2 - 2026-08-25

- Confine `wp kjeks scan-config --output` to the uploads `kjeks/` directory and write via `WP_Filesystem`, preventing writes to arbitrary locations.
- Escape inline gated-script output to prevent a `</script>` breakout in the served markup.
- Ship only the `.pot` template in the distributed zip; compiled translations now come from translate.wordpress.org.
- Correct the readme `Contributors` username to the WordPress.org plugin owner.
- Documentation: cover single-site and Multisite, and clarify that the scanner is a standalone tool.

## 1.1.1 - 2026-08-22

- Fully remove the GitHub self-updater (drop the init and the composer dependency); WordPress.org is now the sole update source.
- Build the GitHub release zip identically to the WordPress.org distribution — same files, no self-updater, `composer.json` included.

## 1.1.0 - 2026-08-22

- Prepare the plugin for the WordPress.org plugin directory: add a `.distignore` and WordPress.org deploy + asset-update GitHub Actions.
- Pass Plugin Check: add a direct-file-access guard and rely on WordPress 6.8+ just-in-time translation loading (drop the manual `load_plugin_textdomain()` call).
- Exclude the GitHub self-updater from the WordPress.org build; GitHub-hosted installs kept the updater.
- Link the readme to the [documentation site](https://kjeks.soderlind.no) and the Kjeks add-ons.

## 1.0.8 - 2026-08-22

- Harden the Restricted Site Access bypass: send no-cache headers on a keyed request so an unrestricted response can never be cached and served to the public, and accept the key only from the `X-Kjeks-Key` header (no query-string fallback) so it can't leak via logs, Referer, or history.

## 1.0.7 - 2026-08-22

- Restricted Site Access compatibility: a request carrying a valid scanner key bypasses the login/IP restriction, so the discovery scanner can reach the `scan-config` and `import` endpoints and load restricted front-end pages. Requests without a valid key stay restricted.

## 1.0.6 - 2026-08-22

- Manage the scanner key from Network Admin → Cookie Consent → Settings: view, copy, regenerate, or clear it — no WP-CLI required.

## 1.0.5 - 2026-08-22

- Add a shared scanner key for the `scan-config` and `import` REST endpoints: send it in the `X-Kjeks-Key` header (or `scan_key` query argument) so CI authenticates even behind proxies that strip the `Authorization` header. Manage it with `wp kjeks scan-key`. Application-password / capability auth still works as a fallback.

## 1.0.4 - 2026-08-21

- Track the compiled `build/` assets in version control so Composer source installs (e.g. `~1.0.3` from a VCS/registry) ship the admin and banner scripts, fixing a 404 on `build/network.js` after activation.

## 1.0.3 - 2026-08-20

- Network review: replace the truncated site-count tooltip with a scrollable, numerically sorted popover that closes on Escape or click outside, so all sites are reachable.
- Network review: allow assigning the Necessary category when reviewing cookies.

## 1.0.2 - 2026-08-20

- Add suggested cookie and consent text to the WordPress Privacy Policy Guide (Settings → Privacy).
- Optionally append the live cookie declaration to the site's privacy policy page — a network opt-in (off by default) with a dedup guard so it isn't duplicated when the Cookie declaration block or `[kjeks_cookie_declaration]` shortcode is already on the page.
- Add a "Show the cookie declaration on the privacy policy page" toggle to the network admin Settings tab.

## 1.0.1 - 2026-08-20

- Register two block-editor blocks via `block.json` metadata: "Cookie settings" (`kjeks/preferences`) and "Cookie declaration" (`kjeks/cookie-declaration`), and add an editor script so both appear in the inserter.
- Register a shared `kjeks-banner` stylesheet so the blocks' `style` handle resolves on the front end and in the editor's ServerSideRender preview.
- Reorganize the network admin into Cookies, Banner, and Settings tabs.
- Add JavaScript translations for the block editor.

## 1.0.0 - 2026-08-20

- First stable release.
- Add a Norwegian Bokmål (`nb_NO`) translation and refresh the translation template (`.pot`).
- Add JavaScript (admin UI) translations via `wp i18n make-json`, wired with `wp_set_script_translations` and an `i18n-map.json`.
- Document why the consent banner may not appear (Global Privacy Control, a prior choice, or browser blockers).

## 0.9.0 - 2026-08-20

- Scanner config (`scan-config`) now auto-selects representative URLs per site via `WP_Query` — the home page, newest post and page, the posts archive, and pages whose content shows an embed / inline-script signal — deduped and capped (default 10). Omit `paths` to auto-select; explicit paths still override.
- Add a `cap` REST parameter and `--cap` CLI option, plus `kjeks_scan_url_cap` and `kjeks_scan_paths` filters.

## 0.8.0 - 2026-08-19

- Self-updates from GitHub releases via the `wordpress-github-updater` library (bundled `plugin-update-checker`). Define the optional `KJEKS_GITHUB_TOKEN` constant for private repositories or higher GitHub API rate limits.
- Add GitHub Actions workflows to build and attach the release ZIP on published releases and on manual dispatch.

## 0.7.0 - 2026-08-19

- Add single-site support: the consent admin registers under the normal admin menu with `manage_options` on non-multisite installs (network admin with `manage_network_options` on multisite). The review UI drops the "network" wording and hides the Sites column on single-site.

## 0.6.0 - 2026-08-19

- **Breaking:** remove the per-site consent admin screen and the `/kjeks/v1/site-config` REST route. All consent administration now happens under Network Admin (`manage_network`).
- Default the banner's privacy policy link to each site's core privacy page (`get_privacy_policy_url()`); add the `kjeks_privacy_url` filter for per-site/per-locale overrides.
- Drop per-site local trackers and content overrides. Legacy `kjeks_site_trackers` / `kjeks_site_content` / `kjeks_site_overrides` options are no longer read and are cleaned up on uninstall.

## 0.5.0 - 2026-08-19

- Add a network-admin toggle to show the consent banner until a visitor makes a choice (default on).
- Hide the redundant “Cookie settings” trigger while the banner is visible; it appears after a choice, when the banner is disabled, or for returning visitors.
- Honor programmatically injected consent records whose policy version / blog id are numeric (compare with string coercion), fixing consent-state scanning by the discovery scanner.

## 0.4.0 - 2026-08-19

- Move the discovery scanner into its own repository, [soderlind/kjeks-scanner](https://github.com/soderlind/kjeks-scanner); it integrates over the unchanged REST `scan-config` / `import` contract. No plugin runtime change.
- Document the plugin family: add an ecosystem overview (kjeks, kjeks-google, kjeks-ai-reviewer, kjeks-scanner) with integration seams to `docs/architecture.md`.

## 0.3.0 - 2026-08-19

- Add a network-admin tab extension seam so add-ons can contribute their own tabs to the Kjeks network screen via the `kjeks.networkAdminTabs` JavaScript filter.
- Fix the `KJEKS_VERSION` constant, which was left at `0.1.0`.

## 0.2.0 - 2026-08-19

- Split the network tracker registry into its own module, separate from network settings.
- Resolve the effective inventory through a single module; remove the unused per-site override path.
- Add a Tracker identity rule so the same cookie aggregates consistently across sites.

## 0.1.0 - 2026-08-19

- Initial release: multisite runtime, prior-blocking API, accessible consent banner, network + per-site admin, REST and WP-CLI, discovery scanner, tests, and docs.
