<?php

declare(strict_types=1);

use Cbox\Risk\Signals\MxRecordSignal;
use Cbox\Risk\Signals\StopForumSpamSignal;
use Cbox\Risk\Signals\TorExitSignal;
use Cbox\Risk\Signals\VelocitySignal;
use Cbox\Risk\Testing\FakeMailDomainResolver;
use Cbox\Risk\Testing\FakeTorExitNodes;
use Cbox\Risk\ValueObjects\RiskContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function at(string $email): RiskContext
{
    return new RiskContext(action: 'register', ip: '203.0.113.9', email: $email);
}

// ---- MxRecordSignal ---------------------------------------------------------

it('flags an email domain with no mail server', function (): void {
    $signal = new MxRecordSignal(new FakeMailDomainResolver(withoutMx: ['nomail.test']));

    expect($signal->evaluate(at('user@nomail.test'))?->key)->toBe('email.no_mx')
        ->and($signal->evaluate(at('user@example.com')))->toBeNull(); // has MX
});

// ---- TorExitSignal ----------------------------------------------------------

it('flags a Tor exit node as a moderate signal', function (): void {
    $signal = new TorExitSignal(new FakeTorExitNodes(['185.220.101.1']));

    expect($signal->evaluate(new RiskContext(action: 'login', ip: '185.220.101.1'))?->points)->toBe(20.0)
        ->and($signal->evaluate(new RiskContext(action: 'login', ip: '203.0.113.9')))->toBeNull();
});

// ---- VelocitySignal ---------------------------------------------------------

it('scores request velocity once the threshold is exceeded', function (): void {
    $signal = new VelocitySignal(Cache::store(), 'secret', windowSeconds: 300, threshold: 3);
    $ctx = new RiskContext(action: 'register', ip: '198.51.100.7');

    // First 3 are under the threshold.
    expect($signal->evaluate($ctx))->toBeNull();
    expect($signal->evaluate($ctx))->toBeNull();
    expect($signal->evaluate($ctx))->toBeNull();

    // The 4th trips it.
    $result = $signal->evaluate($ctx);
    expect($result?->key)->toBe('velocity')
        ->and($result?->meta['count'] ?? null)->toBe(4);
});

it('stores the IP only as an HMAC, never raw', function (): void {
    $signal = new VelocitySignal(Cache::store(), 'secret', threshold: 0);
    $signal->evaluate(new RiskContext(action: 'register', ip: '198.51.100.9'));

    // No cache key contains the raw IP.
    $expected = 'cbox-risk:vel:register:'.hash_hmac('sha256', '198.51.100.9', 'secret');
    expect(Cache::has($expected))->toBeTrue();
});

// ---- StopForumSpamSignal (opt-in, external, cached, fail-open) ---------------

it('flags an IP that StopForumSpam knows, and caches the lookup', function (): void {
    Http::fake(['api.stopforumspam.com/*' => Http::response(['success' => 1, 'ip' => ['appears' => 1, 'frequency' => 12]])]);

    $signal = new StopForumSpamSignal(Cache::store());
    $ctx = new RiskContext(action: 'register', ip: '45.9.9.9');

    expect($signal->evaluate($ctx)?->meta['frequency'] ?? null)->toBe(12);

    // Second call is served from cache (no extra HTTP request).
    $signal->evaluate($ctx);
    Http::assertSentCount(1);
});

it('fails open when StopForumSpam is unreachable', function (): void {
    Http::fake(['api.stopforumspam.com/*' => Http::response('', 500)]);

    $signal = new StopForumSpamSignal(Cache::store());

    expect($signal->evaluate(new RiskContext(action: 'register', ip: '45.9.9.9')))->toBeNull();
});
