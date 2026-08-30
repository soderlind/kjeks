# Config bundle ships a normalized Registry, not the raw option

A Config bundle exports the Registry (`kjeks_network_trackers`) as a normalized slice: only manual and reviewed entries, keyed by Identity, with per-install `sites` lists and unreviewed Observations stripped. On apply it merges by Identity into the target Registry — target entries are kept, bundle entries add or update classification, and local review work is never lost. Every other section replaces its option wholesale; the Registry is the sole merge-by-Identity exception.

We rejected shipping the option verbatim because the raw array carries install-specific blog IDs and unreviewed scan noise that are meaningless — or misleading — on another install, and a wholesale replace would erase the target's own review work. We rejected excluding the Registry entirely because admin classification is the most laborious authored artifact, and re-doing it on every migration defeats the purpose of the bundle.

The trade-off is that export/apply must understand Registry internals (Identity, `source`, `reviewed`) rather than treating it as an opaque blob, and Identity collisions across installs resolve in the bundle's favour for classification fields. The surprise this documents: the bundle is not a byte-for-byte settings dump — one section is deliberately transformed.
