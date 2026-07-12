<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Contracts\IpReputation;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Reads IP levels from a cache map populated by `risk:refresh-ipsum`. The lookup
 * is O(1) against a local cache and returns 0 (clean) when the list hasn't been
 * fetched yet — so the signal is inert, never erroring, until you schedule the
 * refresh.
 */
final class CacheIpReputation implements IpReputation
{
    public const CACHE_KEY = 'cbox-risk:ipsum';

    public function __construct(private readonly Cache $cache) {}

    public function level(string $ip): int
    {
        $map = $this->cache->get(self::CACHE_KEY);

        if (! is_array($map)) {
            return 0;
        }

        $level = $map[$ip] ?? 0;

        return is_int($level) ? $level : 0;
    }
}
