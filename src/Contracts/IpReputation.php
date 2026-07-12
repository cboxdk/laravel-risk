<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

/**
 * A confidence level for how "bad" an IP is. Modeled on stamparm/ipsum's levels:
 * the number of independent blocklists the IP appears on (0 = clean, higher =
 * corroborated by more feeds). Implementations must be fast (local cache) and
 * fail-open (return 0 when unavailable), never per-request network calls.
 */
interface IpReputation
{
    public function level(string $ip): int;
}
