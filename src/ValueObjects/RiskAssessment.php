<?php

declare(strict_types=1);

namespace Cbox\Risk\ValueObjects;

use Cbox\Risk\Enums\Outcome;

/**
 * The result of scoring a request: the total score, the outcome it maps to, and
 * the per-signal breakdown that produced it (each already weighted). The
 * breakdown is what makes the decision auditable — never a black box.
 */
final readonly class RiskAssessment
{
    /**
     * @param  list<SignalResult>  $signals  the signals that fired, weighted
     */
    public function __construct(
        public float $score,
        public Outcome $outcome,
        public array $signals,
    ) {}

    public function isAllowed(): bool
    {
        return $this->outcome === Outcome::Allow;
    }

    /**
     * True when the outcome is at least as severe as the given one.
     */
    public function atLeast(Outcome $outcome): bool
    {
        return $this->outcome->severity() >= $outcome->severity();
    }

    /**
     * The reasons behind the score, for logging or an audit trail.
     *
     * @return list<string>
     */
    public function reasons(): array
    {
        return array_map(static fn (SignalResult $s): string => $s->reason, $this->signals);
    }
}
