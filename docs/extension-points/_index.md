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

The config array is the **host's** list. It's the right place for a signal your own
app owns.

## A signal from a package (the `SignalRegistry` hook)

When the signal ships in a *package* — a premium adaptive-risk plugin, a bespoke
fraud check — it can't reach the host's `config/risk.php`. Register it on the
`SignalRegistry` from the package's own service provider instead, and it joins the
pipeline with **zero host edits**:

```php
use Cbox\Risk\Facades\Risk;

public function boot(): void
{
    Risk::signals()->register(ImpossibleTravelSignal::class, weight: 1.5);
}
```

- Pass an **instance** or a **`class-string`** — a class-string is resolved from the
  container, so the signal gets its dependencies injected (exactly like the config
  path).
- The optional `weight` seeds `config('risk.weights')` for this signal's `key()`.
  If the host *also* sets a weight for that key, the **host's config wins** — an
  operator can always dial a package's signal down (or to `0`) without touching the
  package.
- Registry signals run **alongside** the config signals, after them. Registering
  nothing changes nothing (deny-by-default); registering a class that isn't a
  `Signal` fails loud, never silently.

This is the hook the commercial **risk-plus** plugin uses to light up geo /
impossible-travel and new-device signals on install. Resolve the registry directly
(`app(SignalRegistry::class)`) if you prefer constructor injection over the facade.

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
