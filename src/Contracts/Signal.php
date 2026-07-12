<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * A single risk signal — one thing worth scoring (IP reputation, a disposable
 * email domain, submit timing, …). Signals are cheap, independent, and pure:
 * given a context they return a {@see SignalResult} (points + reason) or null
 * when they have nothing to say. Cost/IO (blocklist lookups) is the signal's
 * concern; keep it cached and fail-open.
 */
interface Signal
{
    /**
     * A stable identifier used for its configured weight and in the breakdown.
     */
    public function key(): string;

    public function evaluate(RiskContext $context): ?SignalResult;
}
