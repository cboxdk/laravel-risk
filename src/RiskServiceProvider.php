<?php

declare(strict_types=1);

namespace Cbox\Risk;

use Cbox\Risk\Console\RefreshIpsumCommand;
use Cbox\Risk\Console\RefreshTorCommand;
use Cbox\Risk\Contracts\DisposableDomains;
use Cbox\Risk\Contracts\IpReputation;
use Cbox\Risk\Contracts\MailDomainResolver;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Contracts\SignalRegistry;
use Cbox\Risk\Contracts\TorExitNodes;
use Cbox\Risk\Http\AssessRequest;
use Cbox\Risk\Registry\DefaultSignalRegistry;
use Cbox\Risk\Signals\HoneypotSignal;
use Cbox\Risk\Signals\IpReputationSignal;
use Cbox\Risk\Signals\VelocitySignal;
use Illuminate\Contracts\Cache\Repository as Cache;
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
        $this->app->singleton(TorExitNodes::class, CacheTorExitNodes::class);
        $this->app->singleton(MailDomainResolver::class, SystemMailDomainResolver::class);

        // The package hook: a shared registry other providers push signals into
        // from their boot(). Kept a singleton so every plugin extends one pipeline.
        $this->app->singleton(SignalRegistry::class, static fn (Application $app): SignalRegistry => new DefaultSignalRegistry($app));

        // The velocity signal needs a secret to HMAC IPs; wire it from app.key.
        $this->app->bind(VelocitySignal::class, function (Application $app): VelocitySignal {
            $key = config('app.key');

            return new VelocitySignal(
                $app->make(Cache::class),
                is_string($key) && $key !== '' ? $key : 'cbox-risk',
                $this->configInt('risk.velocity.window', 300),
                $this->configInt('risk.velocity.threshold', 5),
            );
        });

        // Configurable-band signals, wired from their config sections.
        $this->app->bind(IpReputationSignal::class, fn (Application $app): IpReputationSignal => new IpReputationSignal(
            $app->make(IpReputation::class),
            $this->configFloat('risk.ip_reputation.strong_points', 50),
            $this->configFloat('risk.ip_reputation.medium_points', 25),
            $this->configInt('risk.ip_reputation.strong_level', 5),
            $this->configInt('risk.ip_reputation.medium_level', 3),
        ));

        $this->app->bind(HoneypotSignal::class, fn (): HoneypotSignal => new HoneypotSignal(
            $this->configFloat('risk.honeypot_signal.filled_points', 100),
            $this->configFloat('risk.honeypot_signal.too_fast_points', 60),
            $this->configInt('risk.honeypot_signal.min_seconds', 2),
        ));

        $this->app->singleton(RiskScorer::class, function (Application $app): RiskScorer {
            $registry = $app->make(SignalRegistry::class);

            return new WeightedScorer(
                // Config signals (the host's list) plus registry signals (from
                // installed packages). Resolved here at first use — after boot —
                // so every provider has had its chance to register.
                [...$this->signals($app), ...$registry->all()],
                // Host config weights override any defaults a registration supplied.
                array_merge($registry->weights(), $this->floatMap(config('risk.weights', []))),
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
            $this->commands([RefreshIpsumCommand::class, RefreshTorCommand::class]);
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

    private function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    private function configFloat(string $key, float $default): float
    {
        $value = config($key, $default);

        return is_int($value) || is_float($value) ? (float) $value : (is_numeric($value) ? (float) $value : $default);
    }
}
