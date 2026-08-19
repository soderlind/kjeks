=== Kjeks ===
Contributors: soderlind
Tags: cookies, consent, gdpr, privacy, multisite
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent management for WordPress Multisite: per-site tracker inventories, prior blocking, and an accessible consent banner.

== Description ==

Kjeks assists with cookie and tracker consent management on WordPress Multisite. It provides:

* Network-wide tracker definitions with per-site assignments and overrides.
* Per-site tracker inventories, reviewed and classified by administrators.
* Prior blocking of non-essential scripts, inline snippets, pixels, and embeds.
* An accessible, translatable consent banner with equally prominent Accept all / Reject all / Customize.
* A developer API to register integrations against consent categories.

Kjeks assists with consent management. It does not, and cannot, guarantee legal compliance. Discovery is observational and cannot prove the absence of tracking. Consider technologies beyond cookies.

== Installation ==

1. Place the plugin in `wp-content/plugins/kjeks`.
2. Run `composer install --no-dev` and `npm ci && npm run build` in the plugin directory.
3. Network Activate the plugin from **Network Admin → Plugins**.
4. Configure network defaults under **Network Admin → Cookie Consent**.
5. Review each site's inventory under **Settings → Cookie Consent**.

== Changelog ==

= 0.1.0 =
* Initial Phase 1 release: multisite runtime, blocking API, admin registry, adapters, tests, docs.
