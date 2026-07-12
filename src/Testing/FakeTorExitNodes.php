<?php

declare(strict_types=1);

namespace Cbox\Risk\Testing;

use Cbox\Risk\Contracts\TorExitNodes;

/**
 * A deterministic Tor exit list for tests — empty by default; pass the IPs that
 * should be treated as exit nodes.
 */
final class FakeTorExitNodes implements TorExitNodes
{
    /**
     * @param  list<string>  $exits
     */
    public function __construct(private array $exits = []) {}

    public function contains(string $ip): bool
    {
        return in_array($ip, $this->exits, true);
    }
}
