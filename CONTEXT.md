# Kjeks — Cookie Consent for WordPress

Consent-management plugin for WordPress (single-site or Multisite): per-site tracker inventories, prior blocking of non-essential technologies, and an accessible consent banner. Assists with consent management; it does not claim automatic legal compliance.

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

**Registry**:
The network-wide store of Tracker definitions and aggregated discoveries — one entry per cookie across the whole network, recording every site it appeared on. The source the per-site Inventory is resolved from.
_Avoid_: Catalog, list

**Inventory**:
The effective set of Trackers resolved for one site (`blog_id`) — the site-scoped slice of the Registry. Authoritative for that site's consent UI and cookie declaration.
_Avoid_: List, catalog

**Observation**:
A single item recorded by a discovery scan, before any admin review. Observational only — cannot prove the absence of tracking.
_Avoid_: Finding, hit, result

**Identity**:
The rule for when two Observations are the same Tracker — derived from name, storage type, and domain. The Registry keys on it, so aggregation collapses duplicates.
_Avoid_: Key, hash

**Review**:
The admin act of classifying an Observation into the Registry (assigning Category, provider, purpose, etc.). Discoveries are never auto-classified as `necessary`.
_Avoid_: Approve (approve = the import gate specifically), triage

**Policy version**:
A per-site integer identifying the current consent configuration. Bumping it invalidates prior consent and re-prompts visitors.
_Avoid_: Config hash, revision

**Consent record**:
The visitor's stored choice: category selections + timestamp + policy version + site identifier. Stored client-side only (cookie + `localStorage` mirror); never as identifiable server-side records.
_Avoid_: Consent log, audit record
