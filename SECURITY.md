# Security Policy

## Reporting a vulnerability

**Do not open a public issue.** Report privately via
[GitHub Private Vulnerability Reporting](https://github.com/cboxdk/laravel-risk/security/advisories/new)
(repository → **Security** → **Report a vulnerability**). This is a pre-1.0,
best-effort open-source project; we'll respond as promptly as we can and coordinate
disclosure with you. Good-faith research under this policy is authorized (safe harbor).

Especially wanted: a **signal bypass** (input that should score as risky but
doesn't), or a **false-positive amplifier** (a way to make the scorer block
legitimate users). Include the input, the expected vs actual outcome, and the
config.

## Scope reminder

This is a scoring **aid**, defense in depth — not a complete anti-abuse solution.
It must be paired with rate limiting, authentication, and (where relevant) a WAF.
See the "What this is — and isn't" and GDPR sections of
[docs/security.md](docs/security/index.md).

## Supported versions

Security fixes target the latest release. During `0.x`, only the latest tag.
