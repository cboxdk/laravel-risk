<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Contracts\DisposableDomains;

/**
 * A set-membership check over a normalized domain list. Load it from the bundled
 * starter list, a refreshed cache file, or a custom set — the source is the
 * caller's choice; this only answers membership, fast.
 */
final class ListDisposableDomains implements DisposableDomains
{
    /** @var array<string, true> */
    private array $set = [];

    /**
     * @param  iterable<string>  $domains
     */
    public function __construct(iterable $domains = [])
    {
        foreach ($domains as $domain) {
            $normalized = strtolower(trim($domain));

            if ($normalized !== '' && ! str_starts_with($normalized, '#')) {
                $this->set[$normalized] = true;
            }
        }
    }

    /**
     * Build from a newline-delimited file (the bundled or refreshed list).
     */
    public static function fromFile(string $path): self
    {
        $lines = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];

        return new self($lines);
    }

    public function contains(string $domain): bool
    {
        return isset($this->set[strtolower(trim($domain))]);
    }
}
