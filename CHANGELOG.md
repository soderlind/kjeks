# Changelog

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
