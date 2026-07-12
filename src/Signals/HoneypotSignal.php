<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * The spatie/laravel-honeypot technique as a scored signal. Two tells, both
 * supplied on the context's attributes by the host's form:
 *
 *  - `honeypot` — an invisible field a human never fills. Non-empty ⇒ a bot.
 *  - `form_rendered_at` — a unix timestamp of when the form was rendered (the
 *    host should sign/encrypt it). A submit faster than `min_seconds` is a bot.
 *
 * A filled honeypot is dispositive (high points); fast timing is strong but not
 * alone conclusive, so it accumulates with other signals.
 */
final class HoneypotSignal implements Signal
{
    public function __construct(
        private readonly float $filledPoints = 100.0,
        private readonly float $tooFastPoints = 60.0,
        private readonly int $minSeconds = 2,
    ) {}

    public function key(): string
    {
        return 'honeypot';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        $honeypot = $context->attribute('honeypot');

        if (is_string($honeypot) && trim($honeypot) !== '') {
            return new SignalResult($this->key(), $this->filledPoints, 'honeypot field was filled (bot)');
        }

        $renderedAt = $context->attribute('form_rendered_at');

        if (is_int($renderedAt) || (is_string($renderedAt) && ctype_digit($renderedAt))) {
            $elapsed = time() - (int) $renderedAt;

            if ($elapsed >= 0 && $elapsed < $this->minSeconds) {
                return new SignalResult(
                    $this->key(),
                    $this->tooFastPoints,
                    "form submitted in {$elapsed}s (faster than {$this->minSeconds}s minimum)",
                    ['elapsed' => $elapsed],
                );
            }
        }

        return null;
    }
}
