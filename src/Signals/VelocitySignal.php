<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Scores request velocity from one origin — the highest-signal, provider-free
 * check. It counts requests per (action, IP) in a sliding window and adds points
 * once the count crosses a threshold, scaling with the overage. The IP is stored
 * only as an HMAC (never raw) with a window TTL, so no durable PII is retained.
 */
final class VelocitySignal implements Signal
{
    public function __construct(
        private readonly Cache $cache,
        private readonly string $secret,
        private readonly int $windowSeconds = 300,
        private readonly int $threshold = 5,
        private readonly float $basePoints = 30.0,
        private readonly float $perOverThreshold = 5.0,
        private readonly float $maxPoints = 60.0,
    ) {}

    public function key(): string
    {
        return 'velocity';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        if ($context->ip === null) {
            return null;
        }

        $bucket = 'cbox-risk:vel:'.$context->action.':'.hash_hmac('sha256', $context->ip, $this->secret);

        // Start the window on first sight (preserving its TTL), then count up.
        $this->cache->add($bucket, 0, $this->windowSeconds);
        $count = (int) $this->cache->increment($bucket);

        if ($count <= $this->threshold) {
            return null;
        }

        $points = min($this->maxPoints, $this->basePoints + ($count - $this->threshold) * $this->perOverThreshold);

        return new SignalResult(
            $this->key(),
            $points,
            "{$count} '{$context->action}' requests from this IP in {$this->windowSeconds}s",
            ['count' => $count],
        );
    }
}
