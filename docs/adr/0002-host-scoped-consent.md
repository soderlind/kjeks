# Consent is scoped per host, not shared across domains

The consent cookie is scoped to the exact host. On subdomain and mapped-domain multisites each distinct domain re-prompts and keeps its own consent, because sites may have different tracker inventories and we do not assume consent transfers between domains. This trades some cross-site UX convenience for a defensible privacy posture and per-site inventory isolation.
