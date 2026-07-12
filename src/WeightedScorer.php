<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\ValueObjects\RiskAssessment;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;
use Throwable;

/**
 * A weighted-additive scorer: each signal contributes points, scaled by its
 * configured weight, and the sum is mapped to an outcome via score thresholds.
 *
 * This model is chosen deliberately over an opaque ML score: every decision is
 * explainable (you can show exactly which signals fired and by how much), which
 * matters for an auth/anti-fraud control that may need to justify a block. It
 * fails open — a signal that throws is skipped, never failing the request — so a
 * flaky blocklist provider can't lock everyone out.
 */
final class WeightedScorer implements RiskScorer
{
    /**
     * @param  iterable<Signal>  $signals
     * @param  array<string, float>  $weights  signal key => multiplier (default 1.0)
     * @param  array<string, float>  $thresholds  outcome value => minimum score
     * @param  list<string>  $allowedIps  IPs that bypass scoring entirely
     * @param  list<string>  $allowedEmailDomains  email domains that bypass scoring
     */
    public function __construct(
        private readonly iterable $signals,
        private readonly array $weights = [],
        private readonly array $thresholds = [],
        private readonly array $allowedIps = [],
        private readonly array $allowedEmailDomains = [],
    ) {}

    public function assess(RiskContext $context): RiskAssessment
    {
        // Allowlists always win: a trusted IP or email domain is never scored.
        if ($this->isAllowed($context)) {
            return new RiskAssessment(0.0, Outcome::Allow, []);
        }

        $results = [];
        $score = 0.0;

        foreach ($this->signals as $signal) {
            $result = $this->evaluate($signal, $context);

            if ($result === null) {
                continue;
            }

            $weight = $this->weights[$result->key] ?? 1.0;
            $weighted = new SignalResult(
                key: $result->key,
                points: $result->points * $weight,
                reason: $result->reason,
                meta: $result->meta,
            );

            $results[] = $weighted;
            $score += $weighted->points;
        }

        return new RiskAssessment($score, $this->outcomeFor($score), $results);
    }

    private function isAllowed(RiskContext $context): bool
    {
        if ($context->ip !== null && in_array($context->ip, $this->allowedIps, true)) {
            return true;
        }

        if ($context->email !== null && $this->allowedEmailDomains !== []) {
            $at = strrpos($context->email, '@');
            $domain = $at === false ? '' : strtolower(substr($context->email, $at + 1));

            if ($domain !== '' && in_array($domain, $this->allowedEmailDomains, true)) {
                return true;
            }
        }

        return false;
    }

    private function evaluate(Signal $signal, RiskContext $context): ?SignalResult
    {
        try {
            return $signal->evaluate($context);
        } catch (Throwable) {
            // Fail open: a broken signal must never block a request on its own.
            return null;
        }
    }

    private function outcomeFor(float $score): Outcome
    {
        $decided = Outcome::Allow;

        foreach (Outcome::cases() as $outcome) {
            $threshold = $this->thresholds[$outcome->value] ?? null;

            if ($threshold !== null && $score >= $threshold && $outcome->severity() > $decided->severity()) {
                $decided = $outcome;
            }
        }

        return $decided;
    }
}
