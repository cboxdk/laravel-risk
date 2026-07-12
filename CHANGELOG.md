# Changelog

All notable changes to `cboxdk/laravel-risk` are documented here. Format:
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning:
[SemVer](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

Initial release.

### Added

- Explainable weighted-additive scoring pipeline: `RiskContext` → `Signal`s →
  `WeightedScorer` → `Outcome` (allow / flag / challenge / step-up / reject), with
  a full per-signal reasons breakdown on every assessment.
- Free-core signals (no paid API, clean licenses): `HoneypotSignal` (field +
  submit timing), `UserAgentSignal` (automated clients, missing headers),
  `DisposableEmailSignal` (bundled list), `MxRecordSignal` (undeliverable email),
  `VelocitySignal` (per-IP request rate, HMAC-stored), `IpReputationSignal`
  (stamparm/ipsum, banded by list count), and `TorExitSignal`.
- Opt-in `StopForumSpamSignal` (cached, short-timeout, fail-open; CC BY-NC data).
- `risk:refresh-ipsum` and `risk:refresh-tor` commands to cache the feeds for O(1)
  per-request lookups.
- Configurable per-signal bands (ipsum levels/points, honeypot timing, velocity
  window/threshold) in addition to the weights and outcome thresholds.
- `risk:<action>` middleware and a `RiskAssessed` event as the primary extension
  hook; `Risk` facade for manual scoring.
- Contract-based providers (`IpReputation`, `DisposableDomains`) and cache so hosts
  bring their own data/stores; custom `Signal`s add to the pipeline via config.
- Config-driven weights, thresholds, allowlists, and per-signal enablement.
- Monitor mode by default — scores without acting, so thresholds are calibrated
  before enforcement.

### Security & privacy

- Explainable by design (GDPR Art. 22), monitor-first, friction over hard reject,
  and documented data-minimization/retention guidance. See `docs/security.md`.
- Fails open: a throwing signal is skipped, never blocking a request on its own;
  allowlists always win.

[1.0.0]: https://github.com/cboxdk/laravel-risk/releases/tag/v1.0.0
