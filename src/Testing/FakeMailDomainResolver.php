<?php

declare(strict_types=1);

namespace Cbox\Risk\Testing;

use Cbox\Risk\Contracts\MailDomainResolver;

/**
 * A deterministic MX resolver for tests. By default every domain "has MX"; list
 * the domains that should look undeliverable.
 */
final class FakeMailDomainResolver implements MailDomainResolver
{
    /**
     * @param  list<string>  $withoutMx  domains to report as having no mail server
     */
    public function __construct(private array $withoutMx = []) {}

    public function hasMx(string $domain): bool
    {
        return ! in_array(strtolower($domain), array_map('strtolower', $this->withoutMx), true);
    }
}
