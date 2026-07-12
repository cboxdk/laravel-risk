<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;

/**
 * Runs the signal pipeline for a context and returns a scored, decided
 * assessment.
 */
interface RiskScorer
{
    public function assess(RiskContext $context): RiskAssessment;
}
