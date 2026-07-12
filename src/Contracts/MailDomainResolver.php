<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

/**
 * Answers whether an email domain can actually receive mail (has an MX record).
 * Abstracted so it can be faked in tests and swapped for a cached/remote checker.
 */
interface MailDomainResolver
{
    public function hasMx(string $domain): bool;
}
