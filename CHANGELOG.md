# Changelog

## 0.3.0 - 2026-08-19

- Add a network-admin tab extension seam so add-ons can contribute their own tabs to the Kjeks network screen via the `kjeks.networkAdminTabs` JavaScript filter.
- Fix the `KJEKS_VERSION` constant, which was left at `0.1.0`.

## 0.2.0 - 2026-08-19

- Split the network tracker registry into its own module, separate from network settings.
- Resolve the effective inventory through a single module; remove the unused per-site override path.
- Add a Tracker identity rule so the same cookie aggregates consistently across sites.

## 0.1.0 - 2026-08-19

- Initial release: multisite runtime, prior-blocking API, accessible consent banner, network + per-site admin, REST and WP-CLI, discovery scanner, tests, and docs.
