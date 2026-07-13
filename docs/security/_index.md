---
title: Security & privacy
description: Honest scope, GDPR Article 22, and data-minimization defaults
weight: 8
---

# Security & privacy

## What this is — and isn't

Cbox Risk is a **risk-scoring aid**, not a guarantee. It raises the cost of
automated abuse and gives you a graduated, explainable response. It does **not**:

- stop a determined human attacker with clean signals,
- replace rate limiting, WAF rules, or authentication — run it alongside them,
- inspect the response body (stored XSS, deserialization are the app's job).

Signals are heuristics. Treat the score as evidence to accumulate, never a single
dispositive verdict — and **prefer friction (challenge/step-up) over a hard
reject**, which is recoverable when you're wrong.

## GDPR Article 22 — automated decisions

An IP address is personal data (CJEU, *Breyer*). Scoring a request and then
challenging or blocking it is automated processing of that data, so two rules apply:

- **Lawful basis.** Fraud/abuse prevention is a recognized *legitimate interest*
  (GDPR Recital 47, Art. 6(1)(f)). Document a Legitimate Interest Assessment so you
  can show the balance between your security need and the user's rights.
- **Article 22 — the right not to be subject to a *solely* automated decision** with
  legal or similarly significant effect, and the right to **an explanation and human
  review**. A hard, unexplained auto-reject on a high-impact flow (e.g. locking an
  account) is exactly what Art. 22 restricts.

How this package helps you comply:

1. **Explainability is built in.** Every assessment exposes `reasons()` — the exact
   signals and points. You can show *why*, which a black-box ML score cannot.
2. **Monitor mode by default.** You observe before you act, so you don't ship
   solely-automated blocks unreviewed.
3. **Friction over refusal.** `Challenge`/`StepUp` keep a human in the loop
   (solve a CAPTCHA, verify email) instead of a final automated decision.
4. **Human review path.** Listen to `RiskAssessed`, route high scores to manual
   review or an appeal flow rather than an automated `Reject`, and log the reasons
   for the reviewer.

**Recommendation:** reserve fully-automated `Reject` for extreme scores or
dispositive signals (a filled honeypot), and send everything else to friction or
human review. Keep an override/appeal path.

## Data minimization & retention

IP and fingerprint data is sensitive; collect and keep as little as possible.

- **Hash or truncate IPs** for velocity counters (HMAC-SHA-256 with an app secret,
  or drop the last octet) rather than storing them raw.
- **Short retention.** If you persist a risk trail, give it a short TTL (e.g.
  30–90 days) and auto-prune. Never repurpose fraud data for analytics — that
  collapses the legitimate-interest balance.
- **Ephemeral velocity.** Keep rate/velocity counters in Redis with a TTL only.
- **Fingerprints** hashed and kept only as long as needed for detection.

The package itself stores nothing by default beyond the cached blocklist; any
persistence is your listener's choice — so these defaults are yours to set.

## Pitfalls (and how to avoid over-blocking)

- **CGNAT / shared IPs.** Many mobile and office users share one public IP. Never
  hard-reject on IP reputation alone; score the *combination* (IP + UA + email),
  weight IP moderately, and prefer /24 or ASN aggregation with higher thresholds.
- **Tor/VPN users are not inherently malicious.** Treat as a moderate signal, not an
  auto-reject.
- **Blocklist staleness.** Refresh on a schedule; prefer ipsum's *level*
  (multi-list corroboration) over single-list membership; decay old velocity data.
- **False positives.** Ship monitor-mode, use allowlists, require accumulation, set
  per-action thresholds, and keep an appeal path.

## Reporting

Found a bypass or a signal that misfires in a way that enables abuse? See
[SECURITY.md](../../SECURITY.md) for private disclosure and safe-harbor terms.
