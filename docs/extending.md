---
title: Extending
description: Your own signals, providers, caches, and outcome handling
weight: 6
---

# Extending

Everything is a contract bound in the container, so you extend by adding a class or
rebinding a contract — no forks.

## A custom signal

Implement `Signal` and add it to `config('risk.signals')`. The container resolves
it, so it can declare its own dependencies:

```php
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\{RiskContext, SignalResult};

final class ImpossibleTravelSignal implements Signal
{
    public function __construct(private LastSeenStore $store) {}

    public function key(): string
    {
        return 'geo.impossible_travel';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        // compare current geo vs the account's last-seen location / elapsed time
        return $tooFast
            ? new SignalResult($this->key(), 30, 'implied travel speed exceeds 900 km/h')
            : null;
    }
}
```

```php
// config/risk.php
'signals' => [
    // …built-ins…
    App\Risk\ImpossibleTravelSignal::class,
],
'weights' => ['geo.impossible_travel' => 1.0],
```

## Your own IP-reputation provider

The default reads the ipsum feed from your cache. To use AbuseIPDB, your own
threat intel, or a different store, bind the `IpReputation` contract:

```php
use Cbox\Risk\Contracts\IpReputation;

$this->app->singleton(IpReputation::class, function ($app) {
    return new AbuseIpDbReputation($app['cache']->store('redis'), config('services.abuseipdb.key'));
});
```

The signal is unchanged — it just asks the bound provider for a level. Keep the
provider **cached and fail-open** (return 0 when the source is unavailable).

## Your own disposable-domain source

Bind `DisposableDomains` to read from a database, a refreshed file, or a remote
service:

```php
use Cbox\Risk\Contracts\DisposableDomains;

$this->app->singleton(DisposableDomains::class, fn () => new DatabaseDisposableDomains);
```

## Choosing the cache store

`CacheIpReputation` receives Laravel's default cache repository. To pin it to a
specific store, bind it explicitly:

```php
use Cbox\Risk\{CacheIpReputation};

$this->app->singleton(CacheIpReputation::class, fn ($app) => new CacheIpReputation($app['cache']->store('redis')));
```

## Acting on the outcome

Two hooks:

1. **The `RiskAssessed` event** — register a listener to log, persist, alert, or
   override for a trusted user.
2. **The stashed assessment** — `$request->attributes->get('risk')` in your
   controller to branch on `Outcome` (CAPTCHA, step-up, throttle).

Swap the whole decision by binding your own `RiskScorer` implementation; the
middleware, facade, and every caller resolve the contract.
