---
title: Architecture
description: Context, signals, the weighted scorer, and outcomes
weight: 5
---

# Architecture

Four contracts, wired through the container so every part is swappable and testable.

```
RiskScorer (contract) ── WeightedScorer
   ├─ iterable<Signal>              each: evaluate(RiskContext): ?SignalResult
   │    ├─ HoneypotSignal           (built-in, no IO)
   │    ├─ UserAgentSignal          (built-in, no IO)
   │    ├─ DisposableEmailSignal ── DisposableDomains (contract)
   │    └─ IpReputationSignal ───── IpReputation (contract) ── CacheIpReputation
   ├─ weights      (config: key => multiplier)
   ├─ thresholds   (config: outcome => min score)
   └─ allowlists   (config: ips / email_domains)
```

## RiskContext

An immutable snapshot of what's being scored: `action`, `ip`, `userAgent`,
`email`, `headers`, and an open `attributes` bag for anything extra (honeypot,
submit timing, device fingerprint). Build it with `RiskContext::fromRequest()` or
by hand for non-HTTP flows (queue jobs, imports).

## Signal

One risk check. Pure and independent: given a context it returns a `SignalResult`
(points + reason + meta) or `null`. Signals own their own IO and must be cheap and
**fail-open** — a signal that throws is skipped by the scorer, never failing the
request. Cost (blocklist lookups) is cached locally, never a per-request network
call on the hot path.

## WeightedScorer

`score = Σ (signal.points × weight[signal.key])`, then the total maps to the most
severe `Outcome` whose threshold it meets. Chosen over ML deliberately:

- **Explainable.** You can always answer "why was this blocked?" — the assessment
  carries the exact signals and points. That matters for support, tuning, and
  GDPR Art. 22.
- **Tunable.** Weights and thresholds are plain config; adjust one rule without
  touching the rest.
- **Fail-open and allowlist-first.** A trusted IP/domain bypasses scoring; a broken
  signal contributes nothing.

An ML model, if you want one, plugs in as *one more weighted signal* — never as the
whole decision.

## Outcome

`Allow → Flag → Challenge → StepUp → Reject`, each with a severity rank so you can
compare (`$assessment->atLeast(Outcome::Challenge)`). The mapping from score to
outcome is entirely in config; the package ships sensible defaults.

## RiskAssessed event

Fired after every middleware assessment with the context, the assessment, and the
mode. It's the primary hook: log the reasons, persist a trail, override for a
known-good user, or feed a dashboard — without modifying the pipeline.
