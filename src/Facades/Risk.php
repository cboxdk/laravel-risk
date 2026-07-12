<?php

declare(strict_types=1);

namespace Cbox\Risk\Facades;

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static RiskAssessment assess(RiskContext $context)
 *
 * @see RiskScorer
 */
final class Risk extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RiskScorer::class;
    }
}
