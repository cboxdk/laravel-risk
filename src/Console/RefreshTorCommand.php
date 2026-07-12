<?php

declare(strict_types=1);

namespace Cbox\Risk\Console;

use Cbox\Risk\CacheTorExitNodes;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pulls the official Tor bulk exit list into the cache for O(1) lookups. Exit
 * nodes rotate, so schedule this hourly.
 */
final class RefreshTorCommand extends Command
{
    protected $signature = 'risk:refresh-tor {--url=https://check.torproject.org/torbulkexitlist}';

    protected $description = 'Refresh the Tor exit-node list into the cache';

    public function handle(Cache $cache): int
    {
        $url = $this->option('url');
        $url = is_string($url) && $url !== '' ? $url : 'https://check.torproject.org/torbulkexitlist';

        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            $this->error("Failed to fetch the Tor exit list ({$response->status()}).");

            return self::FAILURE;
        }

        $set = [];

        foreach (preg_split('/\r?\n/', $response->body()) ?: [] as $line) {
            $ip = trim($line);

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $set[$ip] = true;
            }
        }

        $cache->put(CacheTorExitNodes::CACHE_KEY, $set, now()->addHours(3));

        $this->info('Loaded '.count($set).' Tor exit nodes into the cache.');

        return self::SUCCESS;
    }
}
