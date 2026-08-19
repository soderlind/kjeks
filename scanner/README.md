# Kjeks discovery scanner

A standalone Playwright scanner that discovers cookies and similar technologies
across a WordPress Multisite, in each consent state. It runs **separately from
the WordPress runtime** — nothing here is loaded by the plugin.

> Discovery is observational. It records what a real browser encountered; it
> **cannot prove the absence** of tracking, and results vary by geo/IP. Imported
> observations are always **unreviewed** until an administrator classifies them.

## Why real Chromium

The scanner drives a version-pinned, real headless Chromium via Playwright's
CDP-backed API. Only CDP exposes HttpOnly cookies and full network events, and
only a real browser executes third-party code the way a visitor's browser does.
See `../docs/adr/0005-scanner-uses-real-chromium.md`.

## Install

```bash
cd scanner
npm ci
npx playwright install --with-deps chromium
```

## Run a scan

```bash
# From a config file (recommended for multisite):
node src/cli.js --config config.json --out ../scan

# Or a single URL:
node src/cli.js --url https://example.com --blog-id 1 --out ../scan

# Fetch the site list from WordPress over REST (recommended for CI):
KJEKS_USER=admin KJEKS_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
  node src/cli.js --config-url "https://network.example.com/wp-json/kjeks/v1/scan-config" --out ../scan

# Against Cloudflare Browser Run instead of local Chromium (opt-in):
node src/cli.js --config config.json --endpoint "wss://…/browser-run/…"
```

Config shape: `{ "sites": [ { "url", "blog_id", "policy_version", "paths",
"scenarios" } ] }`. Generate it with `wp kjeks scan-config` or fetch it over
REST (below); `paths`/`scenarios` shape is shown in `overlay.example.json`.

### Where the config comes from

Three interchangeable sources:

1. **Static file** — `--config config.json`.
2. **REST** — `--config-url .../wp-json/kjeks/v1/scan-config` fetches the live
   site list (auth: `KJEKS_USER` + `KJEKS_APP_PASSWORD`, caller needs
   `manage_network`). Best for CI: no committed config, new subsites appear
   automatically. Add `--overlay overlay.json` to merge repo-side `paths` and
   `scenarios` by `blog_id` (see `overlay.example.json`).
3. **WP-CLI** — generate a static file locally:

   ```bash
   wp kjeks scan-config --paths=/,/about,/contact > config.json
   wp kjeks scan-config --output=config.json --include=1,3
   ```

The REST endpoint and `wp kjeks scan-config` share the same builder, so they
produce identical output.

For every site the scanner opens a **fresh browser context** per consent state:
`before-choice`, `reject-all`, `only-preferences`, `only-analytics`,
`only-marketing`, `accept-all`. It collects Set-Cookie headers, context cookies
(incl. HttpOnly/Secure/SameSite), `document.cookie`, localStorage,
sessionStorage, IndexedDB names, third-party requests, redirects, scripts,
iframes, and beacon/pixel requests.

## Output and diff

One deterministic JSON file per site at `<out>/<host>.json` (stable key order,
volatile fields normalized). The CLI prints a per-subsite diff (new / changed /
removed) against the previous file and exits non-zero when anything changed, so
CI can flag it for review. Commit `scan/<host>.json` as the baseline.

## Import into WordPress

```bash
KJEKS_USER=admin KJEKS_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
  node src/import.js --site https://network.example.com ../scan/*.json
```

Uses a WordPress application password (HTTP Basic) against
`/wp-json/kjeks/v1/import`; the caller needs `manage_network`. Never commit the
password — pass it via environment / CI secrets. Locally you can instead use
`wp kjeks import <file>`.

## Tests

```bash
BASE_URL=http://plugins.local/ npx playwright test
```

Confirms optional cookies, storage, and third-party requests do not occur
before a consent choice, and that gated scripts stay inert.

## Scheduled scanning

`.github/workflows/scan.yml` runs the scan weekly, uploads the full artifact,
imports observations (if secrets are set), and commits baseline changes. Set
`KJEKS_SITE_URL`, `KJEKS_USER`, and `KJEKS_APP_PASSWORD` as repository secrets.

## Known limitations

- First/third-party classification uses an eTLD+1 approximation (last two
  labels), not the full Public Suffix List.
- Consent states are injected via the shared record schema, not by clicking the
  banner; a UI regression could pass the state matrix yet break the real banner.
- A single run from one location cannot capture geo/consent variations.
