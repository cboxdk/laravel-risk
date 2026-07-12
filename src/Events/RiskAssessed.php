<?php

declare(strict_types=1);

namespace Cbox\Risk\Events;

use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;

/**
 * Fired after a request is scored. Listen for it to log decisions (with the
 * reasons breakdown), persist a risk trail, feed a dashboard, or override the
 * outcome for a known-good user. The primary extension hook of the package.
 */
final readonly class RiskAssessed
{
    public function __construct(
        public RiskContext $context,
        public RiskAssessment $assessment,
        public string $mode,
    ) {}

    /**
     * Whether the host is configured to act on this outcome (enforce mode).
     */
    public function enforcing(): bool
    {
        return $this->mode === 'enforce';
    }
}
