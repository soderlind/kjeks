# Scanner uses real Chromium via Playwright/CDP

The Phase 2 discovery scanner drives a version-pinned, real headless **Chromium** through Playwright's CDP-backed API, run locally in a scheduled GitHub Action. Fidelity is non-negotiable: only a real browser executes third-party scripts, sets cookies, and makes network requests the way a visitor's browser does, and only CDP exposes HttpOnly cookies and full network events (Set-Cookie, redirects, beacons) that `document.cookie` cannot see. A pinned build keeps the deterministic baseline/diff stable.

Considered options:

- **Cloudflare Browser Run** (managed real Chromium over CDP) — kept as an opt-in execution target (`--endpoint wss://…`) since the scanner is written against the CDP/Playwright session API, not launcher-specific glue. Not the default: it adds a paid dependency and an account token as a CI secret, runs from Cloudflare IPs/geo (consent and trackers vary by vantage point), and its Chromium version is managed externally (silent upgrades add diff noise).
- **Cloudflare Kitesurf** — rejected. It is not Chromium but a from-scratch Rust/Wasm agent-first engine implementing only a subset of CDP, with a proxied cookie/network model and partial JS (Boa `eval`), explicitly not pixel-perfect and still in fast-moving beta. It would run trackers differently than a real browser and distort Set-Cookie/HttpOnly/third-party signals — wrong for evidentiary consent scanning, and its weekly changes break deterministic baselines.

Regardless of engine, scanning is observational: it cannot prove the absence of tracking, and results vary by geo/IP, so the diff-and-human-review model remains essential.
