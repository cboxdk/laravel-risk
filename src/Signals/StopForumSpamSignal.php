<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;

/**
 * StopForumSpam reputation for the request IP. Opt-in (the data is CC BY-NC —
 * fine for a self-hoster protecting their own site, not for resale). The API
 * result is cached per IP and the lookup runs with a short timeout and fails
 * open, so a slow or down SFS never blocks a request.
 *
 * Enable by adding it to `config('risk.signals')`. For high volume prefer the
 * downloadable SFS dump behind a custom {@see IpReputation}.
 */
final class StopForumSpamSignal implements Signal
{
    private const ENDPOINT = 'https://api.stopforumspam.com/api';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $cacheTtl = 3600,
        private readonly int $minFrequency = 1,
        private readonly float $points = 40.0,
        private readonly int $timeout = 2,
    ) {}

    public function key(): string
    {
        return 'ip.stopforumspam';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        if ($context->ip === null || filter_var($context->ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $frequency = $this->frequency($context->ip);

        if ($frequency < $this->minFrequency) {
            return null;
        }

        return new SignalResult($this->key(), $this->points, "IP seen on StopForumSpam ({$frequency}x)", ['frequency' => $frequency]);
    }

    private function frequency(string $ip): int
    {
        $cacheKey = 'cbox-risk:sfs:'.$ip;
        $cached = $this->cache->get($cacheKey);

        if (is_int($cached)) {
            return $cached;
        }

        $response = Http::timeout($this->timeout)->acceptJson()->get(self::ENDPOINT, ['ip' => $ip, 'json' => '']);
        $frequency = 0;

        if ($response->successful()) {
            $value = $response->json('ip.frequency');
            $frequency = is_int($value) ? $value : (int) (is_numeric($value) ? $value : 0);
        }

        $this->cache->put($cacheKey, $frequency, $this->cacheTtl);

        return $frequency;
    }
}
