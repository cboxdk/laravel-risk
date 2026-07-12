<?php

declare(strict_types=1);

use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Events\RiskAssessed;
use Cbox\Risk\ValueObjects\RiskAssessment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::post('/join', fn () => response()->json([
        'outcome' => request()->attributes->get('risk')?->outcome->value,
    ]))->middleware('risk:register');
});

it('scores the request and stashes the assessment (monitor mode never blocks)', function (): void {
    config(['risk.mode' => 'monitor']);

    // A blatant bot: filled honeypot -> Reject-level score, but monitor won't block.
    $this->postJson('/join', ['nickname' => 'i am a bot', 'email' => 'x@example.com'])
        ->assertOk()
        ->assertJsonPath('outcome', 'reject');
});

it('blocks a rejected request in enforce mode', function (): void {
    config(['risk.mode' => 'enforce']);

    $this->postJson('/join', ['nickname' => 'i am a bot'])
        ->assertStatus(403);
});

it('lets a clean request through in enforce mode', function (): void {
    config(['risk.mode' => 'enforce']);

    $this->withHeaders(['Accept' => 'text/html', 'Accept-Language' => 'en', 'User-Agent' => 'Mozilla/5.0 (X11) Chrome/126'])
        ->postJson('/join', ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('outcome', 'allow');
});

it('fires the RiskAssessed hook for every request', function (): void {
    Event::fake([RiskAssessed::class]);

    $this->postJson('/join', ['nickname' => 'bot']);

    Event::assertDispatched(RiskAssessed::class, function (RiskAssessed $e): bool {
        return $e->assessment instanceof RiskAssessment && $e->context->action === 'register';
    });
});

it('uses a host-bound custom Ip reputation provider', function (): void {
    // Swapping a provider is how a host brings its own data/cache — verify it's honored.
    $this->app->instance(IpReputation::class, new class implements IpReputation
    {
        public function level(string $ip): int
        {
            return 9; // everything is terrible
        }
    });
    $this->app->forgetInstance(RiskScorer::class);
    config(['risk.mode' => 'enforce']);

    // IP reputation alone (50) is below reject; add a bot UA to cross it.
    $this->withHeaders(['User-Agent' => 'python-requests/2.31'])
        ->postJson('/join', ['email' => 'jane@example.com'])
        ->assertStatus(403);
});
