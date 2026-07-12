<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

/**
 * Knows whether an email domain is a disposable / throwaway provider. The default
 * implementation is backed by a bundled list; a `risk:refresh` command can pull
 * the full community list (amieiro/disposable-email-domains, MIT) into cache.
 */
interface DisposableDomains
{
    public function contains(string $domain): bool;
}
