# Administration is network-only

Kjeks has a single consent-administration surface: **Network Admin → Cookie Consent** on multisite, or the standard admin menu (**Cookie Consent**, `manage_options`) on single-site. There is no per-site consent screen, no per-site REST route, and no per-site store. A network review applies to every site where a cookie was observed; the network registry is the sole source of truth for classifications and banner content.

This follows the plugin's core stance that network review is authoritative — a per-site screen that could only ever show network cookies read-only added surface (a `SiteSettings` page, a `manage_options` REST controller, a `SiteStore`, and content-merge paths) without a matching per-site need in practice. Removing it centralises governance under `manage_network`, shrinks the attack and maintenance surface, and removes a second place classifications could appear to diverge.

The one genuinely per-site need — the banner's **privacy policy link** — is served by WordPress core: `ContentResolver` falls the link back to each site's own `get_privacy_policy_url()` when no network URL is set. Per-site or per-locale copy remains possible programmatically through the `kjeks_privacy_url` and `kjeks_banner_content` filters, so translation plugins and edge cases are covered without an admin screen.

Considered options:

- **Keep the per-site screen** — rejected. In practice no site used per-site local trackers or content overrides, and the screen could not override network classifications anyway, so it was cost without benefit.
- **Move per-site content into the network screen** — rejected. With large networks (tens of sites) a per-site content matrix is unwieldy; the core privacy page plus filters cover the real need.

Legacy per-site options (`kjeks_site_trackers`, `kjeks_site_content`, `kjeks_site_overrides`) are no longer read and are cleaned up, on opt-in, by `Lifecycle/Uninstall`.
