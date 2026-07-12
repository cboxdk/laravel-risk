<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * Scores the request IP against a reputation feed. The higher the level (the more
 * blocklists corroborate it), the more points — but IP reputation is never
 * dispositive on its own (CGNAT and shared IPs cause false positives), so even a
 * high level stays below a single-signal reject and must accumulate.
 */
final class IpReputationSignal implements Signal
{
    public function __construct(
        private readonly IpReputation $reputation,
        private readonly float $strongPoints = 50.0, // level >= strongLevel
        private readonly float $mediumPoints = 25.0,  // level >= mediumLevel
        private readonly int $strongLevel = 5,
        private readonly int $mediumLevel = 3,
    ) {}

    public function key(): string
    {
        return 'ip.reputation';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        if ($context->ip === null) {
            return null;
        }

        $level = $this->reputation->level($context->ip);

        if ($level >= $this->strongLevel) {
            return new SignalResult($this->key(), $this->strongPoints, "IP on {$level} blocklists", ['level' => $level]);
        }

        if ($level >= $this->mediumLevel) {
            return new SignalResult($this->key(), $this->mediumPoints, "IP on {$level} blocklists", ['level' => $level]);
        }

        return null;
    }
}
