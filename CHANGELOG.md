# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed
- Neutral example data in docs and tests.

## [1.0.0] - 2026-11-13

### Added

- Statements (omnisign 1.2): typed in a `textTabs` tab (the statement as its tooltip), 4000 bytes
  at most, as DocuSign's OpenAPI says; a `TEXT` field's `maxLength` as the tab's.

- DocuSign's status polling rule declared as a limit: one request per envelope every 15 minutes
  (`policy.json`: `"scope": "resource"`, `"per": "envelope"`, operation `pollEnvelope`), where the
  notes said no scope could count it. `FetchAction` counts its GET of the envelope under
  `pollEnvelope`, the envelope's reference named, through the guard the factory gives it
  (`Config::guard()->perResource()`, the sandbox's policy when `sandbox` is true), in the same
  limiter as the gateway's guard (which counts `fetch` under the account's limits, once); a second
  poll of an envelope within 15 minutes is a `UsageLimitReachedException`, never sent. It needs
  the resource scope (`PolicyGuard::perResource()`), which omnisense/contracts 1.0 has.
- PHPStan at level max with an empty baseline (`phpstan.neon`, `phpstan-baseline.neon`, as the
  family's harness and CI run it): types only, DocuSign's answers read through an internal `Data`
  that gives the values the casts gave. No behaviour change.

### Changed

- Requires `omnisense/contracts: ^1.0`, `omnisense/omnisign: ^1.0`: stable versions, so `composer require omnisign/docusign` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- `countsPerResource()` is gone, and with it the policy read without its limit per envelope under an omnisense/contracts older than the resource scope: 1.0 has it.

