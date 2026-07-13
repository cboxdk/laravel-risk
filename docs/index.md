---
title: Cbox Risk
description: An explainable, config-driven request risk-scoring pipeline for Laravel
weight: 1
---

# Cbox Risk

Score an incoming request — a registration, a login, a form submit — against many
independent signals, combine them into one explainable number, and map that number
to a graduated outcome: **allow → flag → challenge → step-up → reject**. Every
decision carries the reasons that produced it.

## The mental model

```
RiskContext ── [ Signal, Signal, Signal … ] ──► score ──► Outcome
   (request)        each returns points+reason      Σ×weight   via thresholds
```

- A **`RiskContext`** captures the request (IP, user-agent, email, headers, plus
  anything you add: honeypot, submit timing, fingerprint).
- Each **`Signal`** independently returns points and a human reason, or nothing.
- The **scorer** sums `points × weight`, and the total picks the most severe
  **`Outcome`** band it reaches. Allowlisted IPs/domains bypass scoring.
- Every assessment exposes its **breakdown** — no black boxes.

## Why not just block?

Because a wrong block locks out a real user, and because an automated decision that
materially affects someone carries obligations (GDPR Art. 22 — see
[Security](security/index.md)). Cbox Risk prefers **friction over refusal** (CAPTCHA,
step-up) and is **explainable by construction**, so you can tune it, justify it, and
recover false positives.

## Read next

- [Requirements](requirements.md) — PHP and Laravel versions
- [Installation](getting-started/installation.md)
- [Quickstart](quickstart.md) — protect a route in one line
- [Signals & weights](core-concepts/signals.md) — every signal, its points, and how to tune it
- [Cookbook](cookbook/index.md) — registration, login, tuning, custom actions
- [Architecture](core-concepts/architecture.md) — context, signals, scorer, outcomes
- [Extending](extension-points/index.md) — your own signals, providers, and caches
- [Testing](getting-started/testing.md) — fakes and deterministic scoring
- [Data sources](core-concepts/data-sources.md) — external feeds, licenses, and refresh cadence
- [Security & privacy](security/index.md) — honest scope, GDPR Art. 22, data minimization
