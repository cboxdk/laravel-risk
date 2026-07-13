---
title: Requirements
description: Runtime and framework versions cboxdk/laravel-risk needs
weight: 2
---

# Requirements

Taken directly from the package's `composer.json` — the resolver enforces them,
so this page only explains them.

## Runtime

| Requirement | Version | Why |
|---|---|---|
| PHP | `^8.4` | Uses PHP 8.4 language features throughout. |

The package requires no PHP extensions beyond a default PHP build.

## Framework

| Requirement | Version |
|---|---|
| Laravel (`illuminate/*`) | `^12.0 \|\| ^13.0` |

Registered via package auto-discovery — no manual provider wiring.

## Optional

- A **cache store** (any Laravel cache driver) if you enable signals that memoize
  external lookups; not required for the built-in scoring.
- **External data feeds** (e.g. IP reputation) are opt-in and bring their own
  requirements — see [Data sources](core-concepts/data-sources.md).
