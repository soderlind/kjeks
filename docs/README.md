# Kjeks documentation

Cookie consent management for WordPress, single-site or Multisite. Kjeks assists with consent
management; it does **not** claim automatic legal compliance, and discovery is
observational — it cannot prove the absence of tracking. Consider technologies
beyond cookies (localStorage, pixels, embeds, and similar).

## Contents

- [Installation and network activation](#installation-and-network-activation)
- [Multisite storage model](#multisite-storage-model)
- [Consent lifecycle](#consent-lifecycle)
- [Adding a custom integration](#adding-a-custom-integration)
- [Reviewing discoveries](#reviewing-discoveries)
- [Scanner (Phase 2)](#scanner-phase-2)
- [Verification and release commands](#verification-and-release-commands)
- [Known limitations](#known-limitations)

## Installation and network activation

```bash
composer install --no-dev
npm ci && npm run build
```

On multisite: **Network Admin → Plugins → Network Activate**, then manage everything
under **Network Admin → Cookie Consent**. On a single site: activate normally and
open **Cookie Consent** from the admin menu. Either way there is one consent screen
and no separate per-site screen.

New subsites inherit the network defaults automatically (inherit-at-read), so no
per-site copy runs on site creation.

## Multisite storage model

| Data | Where | Scope |
| --- | --- | --- |
| Network tracker registry (definitions + aggregated discoveries) | `get_site_option('kjeks_network_trackers')` | Network |
| Default banner content | `get_site_option('kjeks_network_content')` | Network |
| Settings (banner visibility, uninstall opt-in) | `get_site_option('kjeks_network_settings')` | Network |
| Policy version | `get_option('kjeks_policy_version')` | Blog |

Discovered cookies **aggregate to the network registry**: the same cookie seen
on many sites becomes one entry that records every `blog_id` it appeared on (its
`sites` list). A network tracker with an empty `sites` list applies to every
site; otherwise it applies only to the sites where it was observed. This scales
review to one decision per cookie instead of one per site.

The visitor's **consent record** is stored client-side only — a first-party
cookie (`kjeks_consent`, host-scoped, `SameSite=Lax`, `Secure` on HTTPS)
mirrored to `localStorage`. No identifiable server-side consent records are
written; no IP is stored. See `docs/adr/0001-client-side-consent-storage.md`.

Consent is **not** shared across domains. On subdomain or mapped-domain
multisites, each host consents independently. See
`docs/adr/0002-host-scoped-consent.md`.

## Consent lifecycle

1. First visit with no valid record: optional categories are denied; nothing
   non-essential runs. The banner offers **Accept all**, **Reject all**, and
   **Customize** with comparable prominence.
2. A choice writes the client-side record and activates the granted categories.
3. Visitors reopen preferences from the floating trigger, the
   `[kjeks_preferences]` block/shortcode, or `kjeks_preferences_link()`. The
   preferences dialog has two tabs: **Your choices** (category toggles) and
   **Cookie declaration** (the reviewed inventory). The
   `[kjeks_cookie_declaration]` shortcode/block remains for a standalone
   privacy page.
4. Withdrawal re-writes the record and fires `kjeks:withdrawn`; already-executed
   scripts cannot be un-run, so integrations should clean up client-side.
5. Bumping the site **policy version** invalidates prior consent and re-prompts.
   See `docs/adr/0004-manual-policy-version.md`.

Global Privacy Control (`navigator.globalPrivacyControl`) is honored as an
automatic reject-all for undecided visitors (filter `kjeks_honor_gpc`). An
explicit Accept still wins.

## Adding a custom integration

Gating is client-side by default so output stays cache-friendly (see
`docs/adr/0003-client-side-gating.md`). Register integrations on the
`kjeks_register_integrations` action.

```php
add_action( 'kjeks_register_integrations', function () {
    // External script gated behind analytics.
    kjeks_register_integration( 'acme', [
        'category'    => 'analytics',
        'src_scripts' => [ [ 'src' => 'https://cdn.example.com/acme.js' ] ],
    ] );

    // Inline snippet delayed until marketing consent.
    kjeks_add_inline_script( 'marketing', "window.dataLayer=window.dataLayer||[];" );
} );

// A registered WordPress script, gated:
add_action( 'wp_enqueue_scripts', function () {
    kjeks_enqueue_script( 'my-handle', plugins_url( 'my.js', __FILE__ ), 'preferences' );
} );

// A gated embed placeholder:
echo kjeks_embed( 'https://www.youtube-nocookie.com/embed/ID', 'marketing', [
    'provider' => 'YouTube',
    'title'    => 'Demo',
] );
```

Client-side hooks:

```js
window.kjeks.onGrant( 'analytics', () => {/* start */} );
window.kjeks.onWithdraw( 'analytics', () => {/* stop, clean up */} );
window.kjeks.openPreferences();
```

Filters: `kjeks_categories` (add optional categories), `kjeks_is_granted`,
`kjeks_banner_content`, `kjeks_embed_html`, `kjeks_honor_gpc`,
`kjeks_server_side_gating`.

Example adapters live in [`examples/`](../examples): generic script, generic
pixel, YouTube/Vimeo embeds, and Plausible (a cookieless, **no-consent-needed**
reference that loads unconditionally).

## Reviewing discoveries

Trackers are never auto-classified as necessary. Discovered cookies are
**unreviewed** until classified.

- **Cookie Consent** (Network Admin on multisite, the admin menu on single-site)
  — the aggregated registry and the only consent UI. Each cookie appears once,
  with a site count, search, status filter
  (pending / reviewed), and **bulk review** (set a category and mark reviewed
  for many at once). A network review applies to every site where the cookie was
  observed.

Only reviewed trackers appear in the consent UI and the generated cookie
declaration (`[kjeks_cookie_declaration]`).

## Scanner

Cookie discovery is handled by a **standalone** Playwright scanner in its own
repository: [soderlind/kjeks-scanner](https://github.com/soderlind/kjeks-scanner).
It runs separately from the WordPress runtime and integrates over REST only — it
is never loaded by the plugin.

- It fetches the site list from `GET /wp-json/kjeks/v1/scan-config` (or a static
  file generated by `wp kjeks scan-config`), drives a real, version-pinned
  Chromium per consent state (`before-choice`, `reject-all`, each optional
  category, `accept-all`), and writes one deterministic JSON file per site with
  a per-subsite diff.
- Results are imported as **unreviewed** trackers via
  `POST /wp-json/kjeks/v1/import` (or `wp kjeks import <file>`) until an
  administrator classifies them.

Generate a static config from the network (lists every site with its URL, blog
id, and current policy version):

```bash
wp kjeks scan-config --paths=/,/about > config.json
```

See the [scanner repository](https://github.com/soderlind/kjeks-scanner) for
running scans, the scheduled GitHub Action, the Browser Run endpoint option, and
limitations.

### Importing scan results

Imported observations are always **unreviewed** until an administrator
classifies them, and are never auto-classified as necessary.

```bash
# From the scanner repository (application password via HTTP Basic; caller needs manage_network):
KJEKS_USER=admin KJEKS_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
  node src/import.js --site https://network.example.com scan/*.json

# Local / manual:
wp kjeks import scan/example.com.json --blog_id=1
```

The REST endpoint is `POST /wp-json/kjeks/v1/import` with `{ blog_id, observations }`.
Never commit application passwords — use environment variables or CI secrets.

## Verification and release commands

```bash
composer install          # dev dependencies
composer lint             # PHPCS (WordPress standard)
composer analyze          # PHPStan level 8
composer test             # Pest unit tests
npm ci && npm run build   # build banner + admin bundles
npm run lint:js           # ESLint
```

## Known limitations

- Gating is JavaScript-driven; with JS disabled the banner does not appear and
  every gated technology stays inert (fail-closed).
- The consent record is client-side only; there is no server-side audit trail.
- Multilingual banner copy relies on translation plugins via
  `kjeks_banner_content`; there is no bespoke per-locale content store yet.
- Discovery is observational and cannot prove the absence of tracking; results
  vary by geo/IP.
- Kjeks does not provide legal advice or guarantee compliance.
