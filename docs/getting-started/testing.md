---
title: Testing
description: Deterministic scoring with fake signals and providers
weight: 7
---

# Testing

Scoring is pure and every source is a contract, so tests are deterministic — no
network, no real blocklists.

## Drive the scorer with a fake signal

```php
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\{RiskContext, SignalResult};
use Cbox\Risk\WeightedScorer;
use Cbox\Risk\Enums\Outcome;

$signal = new class implements Signal {
    public function key(): string { return 'test'; }
    public function evaluate(RiskContext $c): ?SignalResult
    {
        return new SignalResult('test', 90, 'forced');
    }
};

$assessment = (new WeightedScorer([$signal], [], [Outcome::Reject->value => 80]))
    ->assess(new RiskContext(action: 'register', ip: '203.0.113.1'));

expect($assessment->outcome)->toBe(Outcome::Reject);
```

## Bind a fake provider

Swap `IpReputation` / `DisposableDomains` for deterministic fakes:

```php
$this->app->instance(IpReputation::class, new class implements IpReputation {
    public function level(string $ip): int { return 7; }
});
$this->app->forgetInstance(RiskScorer::class); // rebuild the scorer with the fake
```

Or seed the cache the real provider reads:

```php
Cache::put(CacheIpReputation::CACHE_KEY, ['45.9.9.9' => 7], now()->addHour());
```

## Assert the hook fires

```php
Event::fake([RiskAssessed::class]);
$this->postJson('/join', ['nickname' => 'bot']);
Event::assertDispatched(RiskAssessed::class);
```

## What the package's own suite covers

Real detection vectors, not mocks that return success: filled honeypots, sub-2s
submissions, `curl`/`python-requests` user-agents, disposable domains,
ipsum-level bands, allowlist bypass, fail-open on a throwing signal, and the
middleware's monitor-vs-enforce behavior. See `tests/Feature/`.
