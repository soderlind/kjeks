# Kjeks architecture

Current-state map of the Kjeks cookie-consent plugin for WordPress Multisite.
Paths are repository-relative; symbols are exact. Domain terms are defined in
[CONTEXT.md](../CONTEXT.md); decisions and their rationale live in
[docs/adr/](adr). This document describes **implemented** behavior only.

## 1. What it does

Kjeks manages consent for cookies and similar client technologies across a
WordPress network. It blocks non-essential trackers until a visitor consents,
renders an accessible banner, aggregates discovered trackers into a
network-wide registry an admin reviews once, and generates a cookie declaration
from the reviewed set.

**Non-goals (explicit):**

- It does not claim or guarantee legal compliance.
- It does not store server-side consent records (see
  [ADR-0001](adr/0001-client-side-consent-storage.md)).
- The discovery scanner is observational; it cannot prove the absence of
  tracking.

Kjeks is the **core** of a small family of plugins. Its satellites
(`kjeks-google`, `kjeks-ai-reviewer`) and the standalone `kjeks-scanner` are
mapped in [§9 Ecosystem](#9-ecosystem-the-kjeks-family); each integrates only
through the seams documented there.

## 2. Actors and external systems

| Actor | Interacts via |
| --- | --- |
| Site visitor | Consent banner + declaration (frontend) |
| Network administrator | Network Admin → Cookie Consent (aggregated review — the only consent UI) |
| Integration developer | Public PHP API in [src/functions.php](../src/functions.php) + client events |
| `kjeks-scanner` (separate repo) | REST `scan-config` / `import`; runs its own scheduled GitHub Action |
| `kjeks-google` add-on (separate repo) | Public API + `window.kjeks` events |
| `kjeks-ai-reviewer` add-on (separate repo) | `TrackerRegistry` + `kjeks.networkAdminTabs` filter + core AI client |

## 3. Bootstrap and wiring

Entry point [kjeks.php](../kjeks.php) defines constants, loads the Composer
autoloader, registers activation/deactivation to
`Soderlind\Kjeks\Lifecycle\Activation`, and on `plugins_loaded` calls
`Plugin::instance()->boot()`.

[src/Plugin.php](../src/Plugin.php) `boot()` wires every subsystem:

| Wired | Path | Context |
| --- | --- | --- |
| `SiteInitializer` | src/Lifecycle/SiteInitializer.php | always |
| `ImportController` | src/Rest/ImportController.php | always (REST) |
| `ScanConfigController` | src/Rest/ScanConfigController.php | always (REST) |
| `NetworkConfigController` | src/Rest/NetworkConfigController.php | always (REST) |
| `ScriptGate` | src/Blocking/ScriptGate.php | always |
| `Banner` | src/Frontend/Banner.php | always |
| `NetworkAdmin` | src/Admin/NetworkAdmin.php | `is_admin()` |
| `Command` | src/Cli/Command.php | `WP_CLI` — `kjeks import`, `kjeks scan-config` |

`boot()` fires `do_action( 'kjeks_register_integrations' )` on `init` so
integrations register through the public API.

## 4. Components and responsibilities

### Consent core — `src/Consent/`

| Module | Responsibility |
| --- | --- |
| `Categories` | The four canonical categories + `kjeks_categories` filter; `necessary` locked |
| `ConsentSchema` | Wire format of the consent record (`{v,t,b,c}`), shared with the scanner |
| `ConsentState` | Read-only server-side view of the current request's consent (reads the cookie) |
| `PolicyVersion` | Per-site version integer; bumping invalidates prior consent |

### Inventory — `src/Inventory/`

| Module | Responsibility |
| --- | --- |
| `Tracker` | Immutable tracker value object (name, category, party, `sites`, `reviewed`, …) |
| `TrackerIdentity` | The single rule for "same cookie" (name + storage type + domain) |
| `TrackerRegistry` | Network-wide **Registry**: definitions + aggregated discoveries (`kjeks_network_trackers`) |
| `NetworkStore` | Network banner content + settings (banner visibility, uninstall opt-in) |
| `InventoryResolver` | Resolves the effective per-site **Inventory** = the site-scoped slice of the network registry |

### Blocking — `src/Blocking/` and `src/functions.php`

| Module | Responsibility |
| --- | --- |
| `IntegrationRegistry` / `Integration` | Registry of consent integrations and their gated scripts |
| `ScriptGate` | Emits gated scripts inert (`type="text/plain"`); activates client-side |
| `EmbedGate` | Renders accessible, consent-gated embed placeholders |
| `functions.php` | Public API: `kjeks_register_integration`, `kjeks_enqueue_script`, `kjeks_add_inline_script`, `kjeks_embed`, `kjeks_is_granted`, `kjeks_preferences_link` |

### Frontend — `src/Frontend/` + `assets/src/`

| Module | Responsibility |
| --- | --- |
| `Banner` | Localizes `kjeksConfig`, renders `#kjeks-root`, registers shortcodes/blocks |
| `ContentResolver` | Resolves network banner content; falls back the privacy link to core `get_privacy_policy_url()`; `kjeks_privacy_url` / `kjeks_banner_content` filters |
| `CookieDeclaration` | Renders the declaration table from the reviewed Inventory |
| `assets/src/banner.js` | Client runtime: consent record, banner + tabbed dialog, gating activation |

### Admin — `src/Admin/` + `assets/src/admin/`

| Module | Responsibility |
| --- | --- |
| `NetworkAdmin` + `admin/network.js` | The only consent UI: aggregated review table (search, filter, bulk review), banner defaults, banner-visibility toggle |

### REST — `src/Rest/`

| Route | Controller | Capability |
| --- | --- | --- |
| `GET/POST /kjeks/v1/network-config` | `NetworkConfigController` | `manage_network` |
| `POST /kjeks/v1/import` | `ImportController` | `manage_network` |
| `GET /kjeks/v1/scan-config` | `ScanConfigController` | `manage_network` |

### Discovery — `src/Scan/`, `src/Cli/` (+ external scanner)

| Module | Responsibility |
| --- | --- |
| `ScanConfig` | Builds the scanner site list (shared by CLI + REST) |
| `ScanValidator` | Validates untrusted scan payloads into unreviewed `Tracker`s |
| `ScanImporter` | Aggregates observations into the `TrackerRegistry` |
| `Cli/Command` | `wp kjeks import`, `wp kjeks scan-config` |
| [kjeks-scanner](https://github.com/soderlind/kjeks-scanner) | Standalone Playwright scanner (separate repo) — **not loaded by WordPress** |

### Lifecycle — `src/Lifecycle/`

| Module | Responsibility |
| --- | --- |
| `Activation` | Network activation seeding (no per-site copy) |
| `SiteInitializer` | `wp_initialize_site` → inherit-at-read defaults |
| `Uninstall` | Deletes data only when the network opt-in is set |

## 5. Dependency direction

```mermaid
flowchart TD
  Frontend[Frontend / Banner] --> Inventory
  Admin[Admin + REST] --> Inventory
  Scan[Scan / Import] --> Inventory
  Blocking --> Consent
  Frontend --> Consent
  Inventory[Inventory: Registry · Resolver] --> Consent[Consent core]
  Blocking[Blocking: Integrations · Gates]
  Consent --> WP[(WP options / cookies)]
  Inventory --> WP
```

Rule: the Consent core and Inventory do not depend on Admin, REST, or Frontend.
Controllers and the frontend are thin adapters over `InventoryResolver` and the
public API.

## 6. Key flows

### 6a. Consent decision and gating (frontend)

1. `Banner::enqueue()` localizes `kjeksConfig` (categories, policy version, blog
   id, reviewed declaration) and enqueues `build/banner.js`.
2. `ScriptGate` emits every gated script inert (`type="text/plain"
   data-kjeks-category`) via `script_loader_tag` and `wp_footer`
   ([ADR-0003](adr/0003-client-side-gating.md)).
3. `banner.js` reads the `kjeks_consent` cookie/localStorage. No valid,
   current-version record ⇒ show the banner; optional categories denied.
4. On a choice it writes the client-side record (host-scoped cookie,
   [ADR-0002](adr/0002-host-scoped-consent.md)) and **activates** inert scripts
   and embeds for granted categories, dispatching `kjeks:granted` /
   `kjeks:withdrawn`.
5. Bumping `PolicyVersion` or GPC (`navigator.globalPrivacyControl`) changes step
   3's outcome.

The consent record is **client-owned**; the server only ever reads it
(`ConsentState`).

### 6b. Discovery → review → declaration

1. The [scanner](https://github.com/soderlind/kjeks-scanner) drives real Chromium across consent states
   ([ADR-0005](adr/0005-scanner-uses-real-chromium.md)) and writes deterministic
   per-site JSON.
2. Import (`POST /kjeks/v1/import` or `wp kjeks import`) →
   `ScanValidator` → `ScanImporter::import()` →
   `TrackerRegistry::merge_observations()`. Observations aggregate by
   `TrackerIdentity`; each records the `blog_id` in its `sites` list and stays
   **unreviewed**.
3. Network admin classifies each once (`NetworkConfigController` bulk review).
4. `InventoryResolver::scoped_network_trackers()` exposes the site-scoped set;
   `reviewed()` filters to reviewed; `CookieDeclaration` and `banner.js` render
   only those.

## 7. Boundaries and invariants

| Invariant | Enforced in | Verified by |
| --- | --- | --- |
| Nothing is auto-classified `necessary` | `Tracker::from_array` (falls back to `marketing`), `ScanValidator` (ignores incoming category) | tests/Unit/TrackerTest.php, tests/Unit/ScanTest.php |
| Only reviewed trackers are public | `InventoryResolver::reviewed()` | tests/Unit/InventoryResolverTest.php |
| Network review is the only review surface; sites have no consent UI | Only `NetworkConfigController` (`manage_network`) writes reviews; no per-site store or route | tests/Unit/InventoryResolverTest.php ("serves a reviewed network tracker as-is") |
| A cookie appears only where observed | `InventoryResolver::scoped_network_trackers()` | tests/Unit/InventoryResolverTest.php ("scopes … only the sites where observed") |
| Same cookie aggregates once | `TrackerIdentity::for` + `TrackerRegistry::merge_observations` | tests/Unit/ScanTest.php ("collapses the same cookie") |
| Non-essential code stays inert pre-consent | `ScriptGate`, `EmbedGate`; client activation | kjeks-scanner: tests/no-tracking-before-consent.spec.js |
| Consent stored client-side only | `ConsentState` (read-only), no write path | [ADR-0001](adr/0001-client-side-consent-storage.md) |
| Uninstall never deletes data without opt-in | `Lifecycle/Uninstall::run()` | — |

Prohibited: the Inventory/Consent core must not depend on Admin/REST/Frontend;
the scanner must not be loaded by the WordPress runtime.

## 8. Where common changes go

| Change | Files |
| --- | --- |
| Add an optional category | `kjeks_categories` filter (consumes `Categories::all()`) |
| Register a gated integration | `kjeks_register_integration()` in a `kjeks_register_integrations` handler; see [examples/](../examples) |
| Change banner/dialog UI | assets/src/banner.js, assets/src/banner.css → `npm run build` |
| Change review UI | assets/src/admin/network.js → `npm run build` |
| Add a REST field | the relevant `src/Rest/*Controller.php` |
| Change what the scanner collects | [kjeks-scanner](https://github.com/soderlind/kjeks-scanner): src/collect.js, src/scan.js |
| Add a WP-CLI subcommand | `src/Cli/Command.php` + registration in `src/Plugin.php` |

## 9. Ecosystem (the kjeks family)

Kjeks is the **core**; two add-on plugins and a standalone scanner extend it,
each in its own repository. Every satellite depends on Kjeks; **Kjeks depends on
none of them**. Integration happens only through the stable seams below — never
through private internals.

| Repository | Role | Integrates via | Requires Kjeks |
| --- | --- | --- | --- |
| [kjeks](https://github.com/soderlind/kjeks) | Core: consent runtime, registry, review, declaration | — | — |
| [kjeks-google](https://github.com/soderlind/kjeks-google) | Google Consent Mode v2 + GTM/GA container gating | Public PHP API + `window.kjeks` events | Yes (`Requires Plugins: kjeks`) |
| [kjeks-ai-reviewer](https://github.com/soderlind/kjeks-ai-reviewer) | AI-assisted, advisory classification of unreviewed trackers | `TrackerRegistry` (guarded) + `kjeks.networkAdminTabs` filter + core AI client | Yes (`Requires Plugins: kjeks`) |
| [kjeks-scanner](https://github.com/soderlind/kjeks-scanner) | Standalone Playwright discovery scanner | REST only (`scan-config` + `import`) | No — HTTP client, not a WP plugin |

```mermaid
flowchart LR
  google[kjeks-google] -->|PHP API + events| core[(kjeks core)]
  ai[kjeks-ai-reviewer] -->|registry + JS filter| core
  scanner[kjeks-scanner] -. REST scan-config / import .-> core
```

### Integration seams (the only supported contracts)

| Seam | Kind | Producer (kjeks) | Consumer |
| --- | --- | --- | --- |
| Public PHP API | functions | [src/functions.php](../src/functions.php) (`kjeks_register_integration`, `kjeks_is_granted`, …) | kjeks-google |
| Client events | JS `CustomEvent` | `assets/src/banner.js` (`kjeks:granted`, `kjeks:withdrawn`) | kjeks-google |
| Network-admin tabs | JS filter | `assets/src/admin/network.js` (`applyFilters( 'kjeks.networkAdminTabs' )`) | kjeks-ai-reviewer |
| Tracker registry | PHP class (guarded) | [src/Inventory/TrackerRegistry.php](../src/Inventory/TrackerRegistry.php), `Tracker::with_review` | kjeks-ai-reviewer |
| Consent record | wire schema | [src/Consent/ConsentSchema.php](../src/Consent/ConsentSchema.php) (`{v,t,b,c}`) | banner.js, scanner |
| Scan config / import | REST | [src/Rest/ScanConfigController.php](../src/Rest/ScanConfigController.php), [src/Rest/ImportController.php](../src/Rest/ImportController.php) | kjeks-scanner |

**Rules the satellites follow:**

- Each guards every cross-plugin call (`function_exists` / `class_exists`) so it
  degrades to inert when Kjeks is inactive — `kjeks-google/includes/Dependency.php`,
  `kjeks-ai-reviewer/src/Dependency.php`.
- `kjeks-google` is an **adapter over the public API** — no direct dependency on
  Kjeks internals. It sets Consent Mode v2 signals to denied, registers the
  GTM/GA container as an inert integration via `kjeks_register_integration`, and
  updates signals on the `kjeks:granted` / `kjeks:withdrawn` events; resolution
  lives in its own `GoogleTagConfig`.
- `kjeks-ai-reviewer` **never writes classifications directly**. It stores
  advisory suggestions separately (`kjeks_ai_suggestions`) and applies one only
  through `Tracker::with_review` when an admin accepts it. Its own map is in
  [kjeks-ai-reviewer/docs/architecture.md](https://github.com/soderlind/kjeks-ai-reviewer/blob/main/docs/architecture.md).
- `kjeks-scanner` is **never loaded by WordPress**. It speaks REST only and
  imports observations as **unreviewed** (see
  [ADR-0005](adr/0005-scanner-uses-real-chromium.md)).

## 10. Open questions / unknowns

- No integration tests run against a real multisite; unit tests mock WordPress
  via Brain Monkey. Behavior is verified manually against a Local site.
- The legacy per-site options `kjeks_site_overrides`, `kjeks_site_trackers`, and
  `kjeks_site_content` may remain in old installs; nothing reads them and they
  are cleaned up (on opt-in) by `Lifecycle/Uninstall`.
