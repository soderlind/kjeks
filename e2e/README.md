# Kjeks acceptance tests

Playwright end-to-end tests that prove the consent contract against a **running**
WordPress site: nothing tracks before consent, and gated scripts/embeds activate
after consent. Covers core and the add-ons (Google, Embeds, Scripting, Social).

## Run

```sh
cd e2e
npm install
npm run install:browsers   # first time only
npm test
```

By default the suite targets `http://plugins.local/subsite29/`. Point it
elsewhere and override per-add-on pages with environment variables:

```sh
BASE_URL=https://staging.example.com/ \
EMBEDS_URL=embeds/ GOOGLE_URL=/ SCRIPTING_URL=/ SOCIAL_URL=social/ \
npm test
```

## What it checks

- **Core** — banner shows for undecided visitors; Accept all / Reject all persist
  and hide the banner; a returning visitor is not re-prompted; Global Privacy
  Control auto-rejects.
- **Add-ons** — each gated script (or iframe embed) is inert before consent and
  activates once its category is granted.

## Skips, not false passes

The suite is resilient by design. A spec **skips** (rather than fails) when:

- the site is unreachable, or the Kjeks runtime is absent;
- an add-on has no gated markup on the target page (add-on inactive, or the page
  has no matching content).

So on a fresh install the core specs pass and add-on specs skip until you add a
page with the relevant embed/script. Create fixture content on the target site
to exercise the add-on specs.
