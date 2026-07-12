<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\DisposableDomains;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * Flags an email at a disposable / throwaway provider — a strong signal for
 * fake-signup and abuse, since disposable addresses exist to evade accountability.
 */
final class DisposableEmailSignal implements Signal
{
    public function __construct(
        private readonly DisposableDomains $domains,
        private readonly float $points = 40.0,
    ) {}

    public function key(): string
    {
        return 'email.disposable';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        $domain = $this->domainOf($context->email);

        if ($domain === null || ! $this->domains->contains($domain)) {
            return null;
        }

        return new SignalResult($this->key(), $this->points, "email uses a disposable domain ({$domain})", ['domain' => $domain]);
    }

    private function domainOf(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $domain = strtolower(trim(substr($email, $at + 1)));

        return $domain === '' ? null : $domain;
    }
}
