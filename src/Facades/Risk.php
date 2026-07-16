<?php

declare(strict_types=1);

namespace Cbox\Risk\Facades;

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\SignalRegistry;
use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/**
 * @method static RiskAssessment assess(RiskContext $context)
 *
 * @see RiskScorer
 */
final class Risk extends Facade
{
    /**
     * The signal registry — a package hook for contributing extra signals from a
     * service provider's boot(), e.g. `Risk::signals()->register(MySignal::class)`.
     */
    public static function signals(): SignalRegistry
    {
        return Container::getInstance()->make(SignalRegistry::class);
    }

    protected static function getFacadeAccessor(): string
    {
        return RiskScorer::class;
    }
}
