<?php

declare(strict_types=1);

use Cbox\Risk\CacheIpReputation;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\Facades\Risk;
use Cbox\Risk\ValueObjects\RiskContext;
use Illuminate\Support\Facades\Cache;

it('allows a clean request through the default pipeline', function (): void {
    $assessment = Risk::assess(new RiskContext(
        action: 'register',
        ip: '203.0.113.20',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/126',
        email: 'jane@example.com',
        headers: ['accept' => 'text/html', 'accept-language' => 'en-US'],
    ));

    expect($assessment->outcome)->toBe(Outcome::Allow)
        ->and($assessment->score)->toBe(0.0);
});

it('accumulates signals to reject an obvious bot registration', function (): void {
    // Bad IP reputation (via cache) + automated UA + disposable email + filled honeypot.
    Cache::put(CacheIpReputation::CACHE_KEY, ['45.9.9.9' => 7], now()->addHour());

    $assessment = Risk::assess(new RiskContext(
        action: 'register',
        ip: '45.9.9.9',
        userAgent: 'python-requests/2.31',
        email: 'x@mailinator.com',
        headers: [],
        attributes: ['honeypot' => 'filled'],
    ));

    expect($assessment->outcome)->toBe(Outcome::Reject)
        ->and($assessment->reasons())->not->toBeEmpty();
});

it('reaches a challenge/step-up band on partial signals', function (): void {
    // Automated UA (45) + disposable email (40) = 85 -> reject; drop honeypot/ip.
    $assessment = Risk::assess(new RiskContext(
        action: 'register',
        ip: '203.0.113.5',
        userAgent: 'curl/8.4.0',
        email: 'user@example.com', // not disposable
        headers: [],
    ));

    // curl UA (45) alone -> Challenge band (>=30).
    expect($assessment->outcome)->toBe(Outcome::Challenge);
});

it('bypasses scoring for an allowlisted IP', function (): void {
    config(['risk.allow.ips' => ['45.9.9.9']]);
    $this->app->forgetInstance(RiskScorer::class);
    Cache::put(CacheIpReputation::CACHE_KEY, ['45.9.9.9' => 7], now()->addHour());

    $assessment = Risk::assess(new RiskContext(
        action: 'register',
        ip: '45.9.9.9',
        userAgent: 'python-requests/2.31',
        email: 'x@mailinator.com',
        attributes: ['honeypot' => 'filled'],
    ));

    expect($assessment->outcome)->toBe(Outcome::Allow)
        ->and($assessment->signals)->toBeEmpty();
});
