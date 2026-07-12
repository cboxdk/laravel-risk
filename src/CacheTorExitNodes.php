<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Contracts\TorExitNodes;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Membership against the Tor exit list cached by `risk:refresh-tor`. Returns
 * false when the list hasn't been fetched, so the signal is simply inert until
 * you schedule the refresh — never an error.
 */
final class CacheTorExitNodes implements TorExitNodes
{
    public const CACHE_KEY = 'cbox-risk:tor-exits';

    public function __construct(private readonly Cache $cache) {}

    public function contains(string $ip): bool
    {
        $set = $this->cache->get(self::CACHE_KEY);

        return is_array($set) && isset($set[$ip]);
    }
}
