<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Contracts\TorExitNodes;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * Flags a request arriving from a Tor exit node. Privacy-conscious real users use
 * Tor too, so this is a deliberately moderate signal that accumulates with others
 * — never a hard block on its own.
 */
final class TorExitSignal implements Signal
{
    public function __construct(
        private readonly TorExitNodes $exits,
        private readonly float $points = 20.0,
    ) {}

    public function key(): string
    {
        return 'ip.tor_exit';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        if ($context->ip === null || ! $this->exits->contains($context->ip)) {
            return null;
        }

        return new SignalResult($this->key(), $this->points, 'request originates from a Tor exit node');
    }
}
