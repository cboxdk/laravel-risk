<?php

declare(strict_types=1);

use Cbox\Risk\CacheIpReputation;
use Cbox\Risk\CacheTorExitNodes;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\Facades\Risk;
use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// ---- Value objects ----------------------------------------------------------

it('builds a RiskContext from a request with normalized headers and email', function (): void {
    $request = Request::create('/register', 'POST', ['email' => 'Dana@Example.com'], server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
        'HTTP_ACCEPT_LANGUAGE' => 'da',
    ]);

    $context = RiskContext::fromRequest($request, 'register', ['honeypot' => '']);

    expect($context->action)->toBe('register')
        ->and($context->userAgent)->toBe('Mozilla/5.0')
        ->and($context->email)->toBe('Dana@Example.com')
        ->and($context->header('Accept-Language'))->toBe('da') // case-insensitive
        ->and($context->attribute('honeypot'))->toBe('');
});

it('ranks and compares outcomes by severity', function (): void {
    expect(Outcome::Reject->severity())->toBeGreaterThan(Outcome::Challenge->severity());

    $assessment = new RiskAssessment(65.0, Outcome::StepUp, [new SignalResult('x', 65, 'why')]);

    expect($assessment->atLeast(Outcome::Challenge))->toBeTrue()
        ->and($assessment->atLeast(Outcome::Reject))->toBeFalse()
        ->and($assessment->isAllowed())->toBeFalse()
        ->and($assessment->reasons())->toBe(['why']);
});

// ---- Config-driven bands ----------------------------------------------------

it('honors configured IP-reputation points', function (): void {
    config(['risk.ip_reputation.strong_points' => 70, 'risk.ip_reputation.strong_level' => 4]);
    $this->app->forgetInstance(RiskScorer::class);
    Cache::put(CacheIpReputation::CACHE_KEY, ['9.9.9.9' => 4], now()->addHour());

    // A clean browser context so only the IP-reputation signal contributes.
    $assessment = Risk::assess(new RiskContext(
        action: 'login',
        ip: '9.9.9.9',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0) Chrome/126',
        email: 'jane@example.com',
        headers: ['accept' => 'text/html', 'accept-language' => 'en'],
    ));

    // 70 points -> StepUp band (>= 60), proving config was applied.
    expect($assessment->outcome)->toBe(Outcome::StepUp);
});

// ---- Allowlist by email domain ---------------------------------------------

it('bypasses scoring for an allowlisted email domain', function (): void {
    config(['risk.allow.email_domains' => ['trusted.test']]);
    $this->app->forgetInstance(RiskScorer::class);

    $assessment = Risk::assess(new RiskContext(
        action: 'register',
        ip: '203.0.113.9',
        email: 'ceo@trusted.test',
        attributes: ['honeypot' => 'filled'], // would otherwise Reject
    ));

    expect($assessment->outcome)->toBe(Outcome::Allow);
});

// ---- Refresh commands -------------------------------------------------------

it('refreshes the ipsum reputation cache from the feed', function (): void {
    Http::fake(['*' => Http::response("# comment\n1.2.3.4\t6\n5.6.7.8\t3\n")]);

    $this->artisan('risk:refresh-ipsum')->assertSuccessful();

    expect(app(CacheIpReputation::class)->level('1.2.3.4'))->toBe(6)
        ->and(app(CacheIpReputation::class)->level('5.6.7.8'))->toBe(3);
});

it('refreshes the Tor exit-node cache from the feed', function (): void {
    Http::fake(['*' => Http::response("185.220.101.1\n185.220.101.2\nnot-an-ip\n")]);

    $this->artisan('risk:refresh-tor')->assertSuccessful();

    expect(app(CacheTorExitNodes::class)->contains('185.220.101.1'))->toBeTrue()
        ->and(app(CacheTorExitNodes::class)->contains('203.0.113.9'))->toBeFalse();
});
