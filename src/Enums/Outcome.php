<?php

declare(strict_types=1);

namespace Cbox\Risk\Enums;

/**
 * What to do with a request given its risk score, from least to most severe. The
 * host decides how to honor each — `Challenge` typically means a CAPTCHA,
 * `StepUp` an extra authentication factor, `Reject` a hard refusal.
 */
enum Outcome: string
{
    case Allow = 'allow';
    case Flag = 'flag';         // allow, but record it for review
    case Challenge = 'challenge'; // require a CAPTCHA / proof-of-work
    case StepUp = 'step_up';    // require an extra auth factor
    case Reject = 'reject';     // refuse outright

    /**
     * Severity rank (higher = more severe), for comparing outcomes.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Allow => 0,
            self::Flag => 1,
            self::Challenge => 2,
            self::StepUp => 3,
            self::Reject => 4,
        };
    }
}
