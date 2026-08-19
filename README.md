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

## Install

```bash
composer install --no-dev
npm ci && npm run build
```

On multisite, **Network Admin → Plugins → Network Activate**, then manage everything
under **Network Admin → Cookie Consent**. On a single site, activate normally and
open **Cookie Consent** from the admin menu.

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
