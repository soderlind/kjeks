# Policy version is a manual per-site integer

Each site carries a manual integer policy version; bumping it invalidates prior consent and re-prompts visitors. The admin UI suggests a bump when consent-relevant configuration changes, but the change is deliberate. We rejected auto-deriving the version from a config hash because trivial edits would silently re-prompt every visitor (surprising churn). The trade-off is that an admin can forget to bump; the suggestion nudge mitigates it.
