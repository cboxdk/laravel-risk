---
title: Signals & weights
description: Every signal, its points, and how to configure and weight it
weight: 3
---

# Signals & weights

The final score is `Σ (signal points × weight)`. Each signal decides its own
**base points** (how bad the observation is); the **weight** in config is how much
you trust that signal. Two dials, both explicit — nothing hidden.

```
score = Σ  points(signal)  ×  weights[signal.key]
outcome = most severe band in `thresholds` that score reaches
```

## The signals

| Key | Fires when | Base points | Config |
|-----|-----------|-------------|--------|
| `honeypot` | Hidden field filled (dispositive) | `filled_points` (100) | `risk.honeypot_signal` |
| `honeypot` | Submitted faster than `min_seconds` | `too_fast_points` (60) | `risk.honeypot_signal` |
| `user_agent` | No User-Agent | 40 | — |
| `user_agent` | Automated client (`curl`, `python-requests`, headless…) | 45 | — |
| `user_agent` | Missing `Accept`/`Accept-Language` | 15 | — |
| `email.disposable` | Domain on the disposable list | 40 | `risk.disposable_domains_path` |
| `email.no_mx` | Domain has no MX/A record (undeliverable) | 50 | — |
| `velocity` | > `threshold` requests per IP in `window`s | 30 + overage (max 60) | `risk.velocity` |
| `ip.reputation` | IP on ≥ `medium_level` blocklists | `medium_points` (25) | `risk.ip_reputation` |
| `ip.reputation` | IP on ≥ `strong_level` blocklists | `strong_points` (50) | `risk.ip_reputation` |
| `ip.tor_exit` | IP is a Tor exit node | 20 | — |
| `ip.stopforumspam` | IP appears on StopForumSpam (opt-in) | 40 | in the signal's constructor |

## Weights

`config('risk.weights')` scales each signal by its `key`. A weight of `0` mutes a
signal without removing it; `>1` amplifies it.

```php
'weights' => [
    'ip.reputation'    => 1.5,  // you trust ipsum a lot
    'user_agent'       => 0.5,  // your audience has many odd but legit UAs
    'email.disposable' => 1.0,
    'velocity'         => 1.0,
    // …
],
```

## IP reputation — what "level" means

The `ip.reputation` signal reads [stamparm/ipsum](https://github.com/stamparm/ipsum),
whose **level is the number of independent blocklists an IP appears on**. One list
could be noise; five lists agreeing is strong corroboration. So the signal has two
bands rather than a single flag:

```php
'ip_reputation' => [
    'strong_level'  => 5,   // on >= 5 lists -> strong_points
    'strong_points' => 50,
    'medium_level'  => 3,   // on >= 3 lists -> medium_points
    'medium_points' => 25,
],
```

Because CGNAT and shared IPs cause false positives, even `strong_points` (50) is
below the single-signal reject threshold (80) — IP reputation must **accumulate**
with another signal to block. Run `risk:refresh-ipsum` daily to keep the list warm.

## Velocity — IP + action, stored as an HMAC

`velocity` counts requests per `(action, IP)` in a sliding window. The IP is stored
only as an HMAC (never raw), with the window as its TTL — no durable PII.

```php
'velocity' => [
    'window'    => 300,  // seconds
    'threshold' => 5,    // points start on the 6th request
],
```

Points scale with the overage (30 base, +5 per request over, capped at 60), so a
burst climbs toward challenge/step-up without a single request hard-blocking.

## Email — disposable list vs deliverability

Two independent email signals:

- **`email.disposable`** — domain membership against a list. The bundled list is a
  small starter set; publish and refresh the full
  [amieiro/disposable-email-domains](https://github.com/amieiro/disposable-email-domains)
  (MIT) list and point `risk.disposable_domains_path` at it. Or bind your own
  `DisposableDomains` (e.g. DB-backed) — see [Extending](../extension-points/_index.md).
- **`email.no_mx`** — the domain can't receive mail at all. Independent of the
  disposable list: a typo domain or a made-up one scores here even if it isn't a
  known disposable provider.

Both firing together (disposable **and** no MX = 40 + 50 = 90) rejects on email
alone — appropriate, since the address is both throwaway and undeliverable.

## Thresholds — score to outcome

```php
'thresholds' => [
    Outcome::Flag->value      => 15,
    Outcome::Challenge->value => 30,
    Outcome::StepUp->value    => 60,
    Outcome::Reject->value    => 80,
],
```

Raise a band to be more permissive (fewer CAPTCHAs), lower it to be stricter. See
[Cookbook → tuning](../cookbook/_index.md) for a calibration workflow.
