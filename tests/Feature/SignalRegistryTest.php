<?php

declare(strict_types=1);

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\Facades\Risk;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * A named signal so a class-string registration has something to resolve.
 */
final class ContainerResolvedSignal implements Signal
{
    public function key(): string
    {
        return 'test.container';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        return new SignalResult($this->key(), 20.0, 'resolved from the container');
    }
}

function testSignal(string $key, float $points): Signal
{
    return new class($key, $points) implements Signal
    {
        public function __construct(private string $key, private float $points) {}

        public function key(): string
        {
            return $this->key;
        }

        public function evaluate(RiskContext $context): ?SignalResult
        {
            return new SignalResult($this->key, $this->points, 'test signal');
        }
    };
}

beforeEach(function (): void {
    // Isolate the registry's contribution from the bundled config signals, so the
    // score reflects only what we register here.
    config()->set('risk.signals', []);
    app()->forgetInstance(RiskScorer::class);
});

it('runs a signal registered on the registry, with no host config edit', function (): void {
    Risk::signals()->register(testSignal('test.always', 90.0));
    app()->forgetInstance(RiskScorer::class);

    $assessment = Risk::assess(new RiskContext('login'));

    expect($assessment->score)->toBe(90.0)
        ->and($assessment->outcome)->toBe(Outcome::Reject)
        ->and(collect($assessment->signals)->pluck('key'))->toContain('test.always');
});

it('applies a default weight supplied at registration', function (): void {
    Risk::signals()->register(testSignal('test.weighted', 10.0), weight: 3.0);
    app()->forgetInstance(RiskScorer::class);

    expect(Risk::assess(new RiskContext('login'))->score)->toBe(30.0);
});

it('lets host config weight override a registration default', function (): void {
    config()->set('risk.weights', ['test.weighted' => 0.5]);
    Risk::signals()->register(testSignal('test.weighted', 10.0), weight: 3.0);
    app()->forgetInstance(RiskScorer::class);

    expect(Risk::assess(new RiskContext('login'))->score)->toBe(5.0);
});

it('resolves a class-string registration from the container', function (): void {
    Risk::signals()->register(ContainerResolvedSignal::class);
    app()->forgetInstance(RiskScorer::class);

    $assessment = Risk::assess(new RiskContext('login'));

    expect(collect($assessment->signals)->pluck('key'))->toContain('test.container');
});

it('fails loud when a non-signal class is registered (deny-by-default)', function (): void {
    Risk::signals()->register(stdClass::class);

    expect(fn () => Risk::signals()->all())->toThrow(InvalidArgumentException::class);
});
