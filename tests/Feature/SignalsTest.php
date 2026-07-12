<?php

declare(strict_types=1);

use Cbox\Risk\CacheIpReputation;
use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\ListDisposableDomains;
use Cbox\Risk\Signals\DisposableEmailSignal;
use Cbox\Risk\Signals\HoneypotSignal;
use Cbox\Risk\Signals\IpReputationSignal;
use Cbox\Risk\Signals\UserAgentSignal;
use Cbox\Risk\ValueObjects\RiskContext;
use Illuminate\Support\Facades\Cache;

function riskCtx(array $overrides = []): RiskContext
{
    return new RiskContext(
        action: $overrides['action'] ?? 'register',
        ip: $overrides['ip'] ?? '203.0.113.9',
        userAgent: $overrides['ua'] ?? 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36',
        email: $overrides['email'] ?? 'real@example.com',
        headers: $overrides['headers'] ?? ['accept' => 'text/html', 'accept-language' => 'en'],
        attributes: $overrides['attributes'] ?? [],
    );
}

// ---- HoneypotSignal ---------------------------------------------------------

it('flags a filled honeypot as dispositive', function (): void {
    $result = (new HoneypotSignal)->evaluate(riskCtx(['attributes' => ['honeypot' => 'i am a bot']]));

    expect($result?->key)->toBe('honeypot')
        ->and($result?->points)->toBe(100.0);
});

it('flags a form submitted faster than the minimum', function (): void {
    $result = (new HoneypotSignal(minSeconds: 2))->evaluate(riskCtx(['attributes' => ['form_rendered_at' => time()]]));

    expect($result?->points)->toBe(60.0)
        ->and($result?->reason)->toContain('faster than');
});

it('passes a genuine, unhurried submission', function (): void {
    $result = (new HoneypotSignal(minSeconds: 2))->evaluate(riskCtx(['attributes' => ['honeypot' => '', 'form_rendered_at' => time() - 30]]));

    expect($result)->toBeNull();
});

// ---- UserAgentSignal --------------------------------------------------------

it('flags a missing User-Agent', function (): void {
    $noUa = new RiskContext(action: 'register', ip: '203.0.113.9', userAgent: null, email: 'a@example.com');

    expect((new UserAgentSignal)->evaluate($noUa)?->reason)->toContain('no User-Agent');
});

it('flags automated clients', function (string $ua): void {
    expect((new UserAgentSignal)->evaluate(riskCtx(['ua' => $ua]))?->key)->toBe('user_agent');
})->with(['curl/8.4.0', 'python-requests/2.31', 'Go-http-client/1.1', 'Scrapy/2.11']);

it('flags a browser UA that is missing Accept headers', function (): void {
    expect((new UserAgentSignal)->evaluate(riskCtx(['headers' => []]))?->reason)->toContain('Accept');
});

it('passes a normal browser request', function (): void {
    expect((new UserAgentSignal)->evaluate(riskCtx()))->toBeNull();
});

// ---- DisposableEmailSignal --------------------------------------------------

it('flags a disposable email domain', function (): void {
    $signal = new DisposableEmailSignal(new ListDisposableDomains(['mailinator.com']));

    $result = $signal->evaluate(riskCtx(['email' => 'throwaway@Mailinator.com'])); // case-insensitive

    expect($result?->key)->toBe('email.disposable')
        ->and($result?->meta['domain'] ?? null)->toBe('mailinator.com');
});

it('passes a normal email domain', function (): void {
    $signal = new DisposableEmailSignal(new ListDisposableDomains(['mailinator.com']));

    expect($signal->evaluate(riskCtx(['email' => 'user@example.com'])))->toBeNull();
});

// ---- IpReputationSignal -----------------------------------------------------

it('scores IP reputation by level band', function (): void {
    $rep = new class implements IpReputation
    {
        public function level(string $ip): int
        {
            return match ($ip) {
                '198.51.100.1' => 6, // strong
                '198.51.100.2' => 3, // medium
                default => 0,
            };
        }
    };

    $signal = new IpReputationSignal($rep);

    expect($signal->evaluate(riskCtx(['ip' => '198.51.100.1']))?->points)->toBe(50.0)
        ->and($signal->evaluate(riskCtx(['ip' => '198.51.100.2']))?->points)->toBe(25.0)
        ->and($signal->evaluate(riskCtx(['ip' => '203.0.113.9'])))->toBeNull();
});

it('reads IP levels from the cache-backed reputation source', function (): void {
    Cache::put(CacheIpReputation::CACHE_KEY, ['10.9.8.7' => 5], now()->addHour());

    expect(app(CacheIpReputation::class)->level('10.9.8.7'))->toBe(5)
        ->and(app(CacheIpReputation::class)->level('1.1.1.1'))->toBe(0); // unknown -> clean
});
