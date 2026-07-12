<?php

declare(strict_types=1);

namespace Cbox\Risk\Console;

use Cbox\Risk\CacheIpReputation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pulls the stamparm/ipsum IP reputation feed (Unlicense / public domain, an
 * aggregate of 30+ blocklists) into the cache so {@see CacheIpReputation} can do
 * per-request O(1) lookups. Schedule it daily — the feed regenerates once a day.
 */
final class RefreshIpsumCommand extends Command
{
    protected $signature = 'risk:refresh-ipsum {--url=https://raw.githubusercontent.com/stamparm/ipsum/master/ipsum.txt}';

    protected $description = 'Refresh the ipsum IP-reputation list into the cache';

    public function handle(Cache $cache): int
    {
        $url = $this->option('url');
        $url = is_string($url) && $url !== '' ? $url : 'https://raw.githubusercontent.com/stamparm/ipsum/master/ipsum.txt';

        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            $this->error("Failed to fetch ipsum feed ({$response->status()}).");

            return self::FAILURE;
        }

        $map = [];

        foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Each line is "<ip>\t<level>".
            $parts = preg_split('/\s+/', trim($line));

            if ($parts !== false && isset($parts[0], $parts[1]) && filter_var($parts[0], FILTER_VALIDATE_IP) !== false) {
                $map[$parts[0]] = (int) $parts[1];
            }
        }

        // TTL slightly over a day so a missed run doesn't leave a gap.
        $cache->put(CacheIpReputation::CACHE_KEY, $map, now()->addHours(30));

        $this->info('Loaded '.count($map).' IPs into the reputation cache.');

        return self::SUCCESS;
    }
}
