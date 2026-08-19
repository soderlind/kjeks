# Kjeks — Cookie Consent for WordPress Multisite

Consent-management plugin for WordPress Multisite: per-site tracker inventories, prior blocking of non-essential technologies, and an accessible consent banner. Assists with consent management; it does not claim automatic legal compliance.

## Language

**Tracker**:
Umbrella term for any consent-relevant client technology — cookies, `localStorage`, `sessionStorage`, IndexedDB, pixels/beacons, third-party scripts, and embeds.
_Avoid_: Cookie (too narrow), script, tag

**Category**:
The consent bucket a tracker belongs to: `necessary`, `preferences`, `analytics`, or `marketing`. `necessary` is always on and cannot be disabled; all optional categories default to disabled.
_Avoid_: Type, group, purpose

**Integration** (a.k.a. **Adapter**):
Code that activates a third-party technology, gated by a Category. Registered through the plugin's developer API.
_Avoid_: Plugin, extension, connector

**Inventory**:
The reviewed set of Trackers for one site (`blog_id`). Authoritative for that site's consent UI and cookie declaration.
_Avoid_: List, catalog, registry (registry = the admin screen, not the data)

**Observation**:
A single item recorded by a discovery scan, before any admin review. Observational only — cannot prove the absence of tracking.
_Avoid_: Finding, hit, result

**Review**:
The admin act of classifying an Observation into the Inventory (assigning Category, provider, purpose, etc.). Discoveries are never auto-classified as `necessary`.
_Avoid_: Approve (approve = the import gate specifically), triage

**Policy version**:
A per-site integer identifying the current consent configuration. Bumping it invalidates prior consent and re-prompts visitors.
_Avoid_: Config hash, revision

**Consent record**:
The visitor's stored choice: category selections + timestamp + policy version + site identifier. Stored client-side only (cookie + `localStorage` mirror); never as identifiable server-side records.
_Avoid_: Consent log, audit record
