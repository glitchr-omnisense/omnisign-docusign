# Changelog

## [Unreleased]

### Added

- Statements (omnisign 1.2): typed in a `textTabs` tab (the statement as its tooltip), 4000 bytes
  at most, as DocuSign's OpenAPI says; a `TEXT` field's `maxLength` as the tab's.

- DocuSign's status polling rule declared as a limit: one request per envelope every 15 minutes
  (`policy.json`: `"scope": "resource"`, `"per": "envelope"`, operation `pollEnvelope`), where the
  notes said no scope could count it. `FetchAction` counts its GET of the envelope under
  `pollEnvelope`, the envelope's reference named, through the guard the factory gives it
  (`Config::guard()->perResource()`, the sandbox's policy when `sandbox` is true), in the same
  limiter as the gateway's guard (which counts `fetch` under the account's limits, once); a second
  poll of an envelope within 15 minutes is a `UsageLimitReachedException`, never sent.
  `DocusignGatewayFactory::countsPerResource()`.
- Needs omnisense/contracts **2f78f0e** (the resource scope, `PolicyGuard::perResource()`). With an
  older kernel (1568f2c, what the sites' vendors hold) `usagePolicy()` reads policy.json without
  the limit per envelope, which that kernel refuses, and the gateway runs as before; that kernel's
  `PolicyExport` reports the file as unreadable.
- PHPStan at level max with an empty baseline (`phpstan.neon`, `phpstan-baseline.neon`, as the
  family's harness and CI run it): types only, DocuSign's answers read through an internal `Data`
  that gives the values the casts gave. No behaviour change.
