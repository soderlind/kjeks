# Consent records are stored client-side only

Kjeks stores each visitor's consent choice (category selections, timestamp, policy version, site identifier) in a first-party cookie mirrored to `localStorage`, and never writes identifiable server-side consent records. We chose this to minimise stored personal data (no IP, no audit table, no data-subject obligations for a consent log) at the cost of not having a server-side audit trail. If a documented legal requirement for server-side proof of consent arises later, revisit this.
