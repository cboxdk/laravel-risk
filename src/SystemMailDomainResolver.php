<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Contracts\MailDomainResolver;

/**
 * Checks deliverability via the system DNS. A domain with no MX record (and no
 * A/AAAA fallback) cannot receive mail — a strong fake-signup signal.
 */
final class SystemMailDomainResolver implements MailDomainResolver
{
    public function hasMx(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        // RFC 5321: with no MX, a sender may fall back to the domain's A/AAAA record.
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
}
