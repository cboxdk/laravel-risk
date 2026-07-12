<?php

declare(strict_types=1);

namespace Cbox\Risk\ValueObjects;

/**
 * One signal's contribution to a risk assessment: the base points it deems the
 * observation worth, plus a human-readable reason so every decision is
 * explainable. The scorer multiplies `points` by the signal's configured weight.
 */
final readonly class SignalResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public float $points,
        public string $reason,
        public array $meta = [],
    ) {}
}
