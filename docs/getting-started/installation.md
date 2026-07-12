---
title: Installation
description: Install, publish config, and schedule the blocklist refresh
weight: 1
---

# Installation

```bash
composer require cboxdk/laravel-risk
php artisan vendor:publish --tag=risk-config
```

The provider auto-registers, aliases the `risk` middleware, and ships the four
free-core signals enabled. It starts in **monitor mode** (`RISK_MODE=monitor`) —
it scores but does not block, so you can calibrate before enforcing.

## Schedule the IP-reputation refresh

The IP-reputation signal reads a locally cached copy of the stamparm/ipsum feed.
Refresh it daily:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('risk:refresh-ipsum')->daily();
```

Run it once now to populate the cache:

```bash
php artisan risk:refresh-ipsum
```

## Requirements

- PHP `^8.4`, Laravel 12 or 13.
- A cache store (any Laravel driver) for IP reputation and velocity data — Redis
  recommended in production.

## Full disposable-email list (optional)

The bundled list is a small starter set. For full coverage, publish and refresh it
from [amieiro/disposable-email-domains](https://github.com/amieiro/disposable-email-domains)
(MIT), then point the config at your copy:

```bash
php artisan vendor:publish --tag=risk-lists
# refresh resources/risk/disposable-domains.txt on a schedule, then:
# RISK_DISPOSABLE_DOMAINS_PATH=resources/risk/disposable-domains.txt
```
