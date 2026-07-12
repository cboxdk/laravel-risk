<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

/**
 * Knows whether an IP is a current Tor exit node. Tor is not inherently malicious,
 * so this is a moderate signal, never a hard block on its own.
 */
interface TorExitNodes
{
    public function contains(string $ip): bool;
}
