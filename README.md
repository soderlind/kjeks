# Kjeks

Cookie consent management for **WordPress** (single site or Multisite): per-site
tracker inventories, prior blocking of non-essential technologies, and an
accessible consent banner.

> Kjeks assists with consent management. It does **not** claim automatic legal
> compliance, and discovery is observational — it cannot prove the absence of
> tracking. Consider technologies beyond cookies (localStorage, pixels, embeds,
> and similar).

- **Requires:** WordPress 6.8+, PHP 8.3+
- **License:** GPL-2.0-or-later
- **Text domain:** `kjeks`

## Features

- Network-wide tracker definitions with per-site assignments.
- Per-site tracker inventories, reviewed and classified by administrators.
- Prior blocking of non-essential scripts, inline snippets, pixels, and embeds —
  nothing runs before consent (client-side gating, so full-page caching still
  works).
- Accessible, translatable banner with equally prominent **Accept all**,
  **Reject all**, and **Customize**; reopen and withdraw from every page.
- Client-side-only consent record — no IP, no server-side consent log.
- Consent scoped per host; mapped domains consent independently.
- A developer API to register integrations against consent categories.

## Consent categories

`necessary` (always on), `preferences`, `analytics`, `marketing`. Optional
categories default to disabled. Add more via the `kjeks_categories` filter.

## Installation

1. Download the latest [`kjeks.zip`](https://github.com/soderlind/kjeks/releases/latest/download/kjeks.zip).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate the plugin. On multisite, **Network Activate** it and configure network defaults under **Network Admin → Cookie Consent**; on a single site, open **Cookie Consent** from the admin menu.

The plugin updates itself automatically via GitHub releases using [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).

## Usage

1. Open **Cookie Consent** (Network Admin on multisite, the admin menu on a single site) and set the banner heading, body text, privacy-policy URL, and accent colour.
2. Declare the trackers your site runs so Kjeks blocks them until consent is given — register them in code with `kjeks_register_integration()` (see [Developer quick start](#developer-quick-start)).
3. Optionally let visitors reopen or read their choices anywhere:
   - Reopen the banner — block `kjeks/preferences` or shortcode `[kjeks_preferences]`.
   - Cookie declaration table — block `kjeks/cookie-declaration` or shortcode `[kjeks_cookie_declaration]`.
4. Publish. Visitors choose **Accept all**, **Reject all**, or **Customize**; Kjeks enforces the choice and loads only what was granted.

To discover what actually loads and review it, pair Kjeks with the [scanner](https://github.com/soderlind/kjeks-scanner) and the [AI Reviewer](https://github.com/soderlind/kjeks-ai-reviewer) add-on.

## Developer quick start

```php
add_action( 'kjeks_register_integrations', function () {
    // External script gated behind the analytics category.
    kjeks_register_integration( 'acme', [
        'category'    => 'analytics',
        'src_scripts' => [ [ 'src' => 'https://cdn.example.com/acme.js' ] ],
    ] );
} );
```

```js
window.kjeks.onGrant( 'analytics', () => {/* start */} );
window.kjeks.onWithdraw( 'analytics', () => {/* stop, clean up */} );
window.kjeks.openPreferences();
```

Example adapters live in [`examples/`](examples): generic script, generic pixel,
YouTube/Vimeo embeds, and Plausible (a cookieless, no-consent-needed reference).

## Banner not showing?

Usually expected behaviour, not a bug. Kjeks skips the banner when:

- **A choice is already stored.** Consent lasts ~6 months (the `kjeks_consent` cookie + `localStorage`). Returning visitors see the small **Cookie settings** trigger instead — clear site data or use a private window to see the banner again.
- **The browser sends Global Privacy Control (GPC).** **Brave sends GPC by default** (as do DuckDuckGo, Firefox's “Tell websites not to sell or share my data”, and some extensions). Kjeks honours GPC by default, so it auto-applies *reject non-essential* and shows only the trigger. Opt out with the `kjeks_honor_gpc` filter.
- **A browser or extension hides cookie notices.** Brave Shields’ *block cookie consent notices*, uBlock Origin’s cookie-notice lists (EasyList Cookie), and similar remove banners client-side — Kjeks can’t override that.
- **The network default banner is off** (`bannerDefaultVisible`) — only the trigger shows.
- **The banner script or storage is blocked** — it needs its script plus cookies/`localStorage`.

## Development

```bash
composer lint       # PHPCS (WordPress standard)
composer analyze    # PHPStan level 8
composer test       # Pest unit tests
npm run build       # build banner + admin bundles
npm run lint:js     # ESLint
```

## Documentation

Full docs — storage model, consent lifecycle, custom integrations, reviewing
discoveries, and known limitations — are in [`docs/README.md`](docs/README.md).
Architecture decisions are recorded in [`docs/adr/`](docs/adr), and the domain
glossary in [`CONTEXT.md`](CONTEXT.md).

## Roadmap

- **Phase 1 (done):** runtime, blocking API, admin registry, adapters, tests,
  docs.
- **Phase 2 (done):** Playwright discovery scanner — now its own repo,
  [`kjeks-scanner`](https://github.com/soderlind/kjeks-scanner) — with
  authenticated REST/WP-CLI import, browser tests confirming no optional storage
  before consent, and a scheduled GitHub Action.
- **Network-aggregated review (done):** discovered cookies aggregate to a single
  network registry, reviewed once with bulk actions.
- **One admin surface (done):** consent is administered from a single screen —
  Network Admin on multisite, the standard admin menu on single-site; the
  separate per-site screen was removed.
- **Single-site support (done):** runs on both single site and Multisite.
- **Add-ons (done):** [`kjeks-google`](https://github.com/soderlind/kjeks-google)
  (Google Consent Mode v2) and
  [`kjeks-ai-reviewer`](https://github.com/soderlind/kjeks-ai-reviewer)
  (AI-assisted classification of unreviewed cookies).
- **Next:** integration tests against a real multisite; Public Suffix List for
  first/third-party classification; an admin nudge when a site has unreviewed
  observations.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
