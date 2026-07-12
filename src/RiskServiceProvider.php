<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Console\RefreshIpsumCommand;
use Cbox\Risk\Contracts\DisposableDomains;
use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Http\AssessRequest;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class RiskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/risk.php', 'risk');

        $this->app->singleton(DisposableDomains::class, function (Application $app): DisposableDomains {
            $path = config('risk.disposable_domains_path');

            return ListDisposableDomains::fromFile(
                is_string($path) && $path !== '' ? $path : __DIR__.'/../resources/disposable-domains.txt'
            );
        });

        $this->app->singleton(IpReputation::class, CacheIpReputation::class);

        $this->app->singleton(RiskScorer::class, function (Application $app): RiskScorer {
            return new WeightedScorer(
                $this->signals($app),
                $this->floatMap(config('risk.weights', [])),
                $this->floatMap(config('risk.thresholds', [])),
                $this->stringList(config('risk.allow.ips', [])),
                $this->stringList(config('risk.allow.email_domains', [])),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/risk.php' => $this->app->configPath('risk.php')], 'risk-config');
        $this->publishes([__DIR__.'/../resources/disposable-domains.txt' => $this->app->resourcePath('risk/disposable-domains.txt')], 'risk-lists');

        $this->app->make(Router::class)->aliasMiddleware('risk', AssessRequest::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RefreshIpsumCommand::class]);
        }
    }

    /**
     * @return list<Signal>
     */
    private function signals(Application $app): array
    {
        $classes = config('risk.signals', []);
        $signals = [];

        if (is_array($classes)) {
            foreach ($classes as $class) {
                if (is_string($class) && is_a($class, Signal::class, true)) {
                    $resolved = $app->make($class);

                    if ($resolved instanceof Signal) {
                        $signals[] = $resolved;
                    }
                }
            }
        }

        return $signals;
    }

    /**
     * @return array<string, float>
     */
    private function floatMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $number) {
            if (is_string($key) && (is_int($number) || is_float($number))) {
                $map[$key] = (float) $number;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $v): bool => is_string($v) && $v !== ''));
    }
}
