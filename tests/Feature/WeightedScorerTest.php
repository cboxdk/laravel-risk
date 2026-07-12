<?php

declare(strict_types=1);

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;
use Cbox\Risk\WeightedScorer;

/**
 * A signal that always contributes fixed points — enough to drive the scorer
 * deterministically.
 */
function signal(string $key, float $points, bool $throws = false): Signal
{
    return new class($key, $points, $throws) implements Signal
    {
        public function __construct(private string $k, private float $p, private bool $throws) {}

        public function key(): string
        {
            return $this->k;
        }

        public function evaluate(RiskContext $context): ?SignalResult
        {
            if ($this->throws) {
                throw new RuntimeException('boom');
            }

            return $this->p === 0.0 ? null : new SignalResult($this->k, $this->p, "{$this->k} fired");
        }
    };
}

function ctx(): RiskContext
{
    return new RiskContext(action: 'register', ip: '203.0.113.9', email: 'a@b.test');
}

$thresholds = [
    Outcome::Flag->value => 15,
    Outcome::Challenge->value => 30,
    Outcome::StepUp->value => 60,
    Outcome::Reject->value => 80,
];

it('sums weighted signals and allows a low score', function () use ($thresholds): void {
    $scorer = new WeightedScorer([signal('a', 5), signal('b', 5)], [], $thresholds);

    $assessment = $scorer->assess(ctx());

    expect($assessment->score)->toBe(10.0)
        ->and($assessment->outcome)->toBe(Outcome::Allow)
        ->and($assessment->isAllowed())->toBeTrue()
        ->and($assessment->signals)->toHaveCount(2);
});

it('applies per-signal weights', function () use ($thresholds): void {
    // base 20 points, weighted x4 = 80 -> Reject.
    $scorer = new WeightedScorer([signal('ip.blocklist', 20)], ['ip.blocklist' => 4.0], $thresholds);

    $assessment = $scorer->assess(ctx());

    expect($assessment->score)->toBe(80.0)
        ->and($assessment->outcome)->toBe(Outcome::Reject);
});

it('maps a score to the most severe threshold met', function () use ($thresholds): void {
    expect((new WeightedScorer([signal('x', 35)], [], $thresholds))->assess(ctx())->outcome)->toBe(Outcome::Challenge)
        ->and((new WeightedScorer([signal('x', 65)], [], $thresholds))->assess(ctx())->outcome)->toBe(Outcome::StepUp)
        ->and((new WeightedScorer([signal('x', 20)], [], $thresholds))->assess(ctx())->outcome)->toBe(Outcome::Flag);
});

it('ignores signals that return null', function () use ($thresholds): void {
    $assessment = (new WeightedScorer([signal('a', 0), signal('b', 40)], [], $thresholds))->assess(ctx());

    expect($assessment->signals)->toHaveCount(1)
        ->and($assessment->score)->toBe(40.0);
});

it('fails open when a signal throws (never blocks on a broken signal)', function () use ($thresholds): void {
    $assessment = (new WeightedScorer([signal('broken', 100, throws: true), signal('ok', 10)], [], $thresholds))->assess(ctx());

    expect($assessment->score)->toBe(10.0)
        ->and($assessment->outcome)->toBe(Outcome::Allow);
});

it('exposes an explainable breakdown of reasons', function () use ($thresholds): void {
    $assessment = (new WeightedScorer([signal('ip.blocklist', 50), signal('email.disposable', 40)], [], $thresholds))->assess(ctx());

    expect($assessment->reasons())->toBe(['ip.blocklist fired', 'email.disposable fired'])
        ->and($assessment->atLeast(Outcome::StepUp))->toBeTrue();
});

it('resolves the RiskScorer contract from the container', function (): void {
    expect(app(RiskScorer::class))->toBeInstanceOf(WeightedScorer::class);
});
