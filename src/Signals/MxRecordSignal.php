<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\MailDomainResolver;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * Flags an email whose domain can't receive mail (no MX/A record) — the address
 * is undeliverable, so it's almost certainly fake. A DNS lookup is cheap but not
 * free; the resolver should cache, and the scorer fails open if it errors.
 */
final class MxRecordSignal implements Signal
{
    public function __construct(
        private readonly MailDomainResolver $resolver,
        private readonly float $points = 50.0,
    ) {}

    public function key(): string
    {
        return 'email.no_mx';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        $email = $context->email;

        if ($email === null) {
            return null;
        }

        $at = strrpos($email, '@');
        $domain = $at === false ? '' : strtolower(trim(substr($email, $at + 1)));

        if ($domain === '' || $this->resolver->hasMx($domain)) {
            return null;
        }

        return new SignalResult($this->key(), $this->points, "email domain has no mail server ({$domain})", ['domain' => $domain]);
    }
}
