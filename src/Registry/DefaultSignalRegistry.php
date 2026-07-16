<?php

declare(strict_types=1);

namespace Cbox\Risk\Registry;

use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\Contracts\SignalRegistry;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The default {@see SignalRegistry}: an ordered list of instances and
 * class-strings, resolved lazily from the container the first time the pipeline
 * is built. Registering a non-signal class is a programming error and fails loud
 * (deny-by-default), never a silently-skipped signal.
 */
final class DefaultSignalRegistry implements SignalRegistry
{
    /**
     * @var list<array{signal: Signal|class-string<Signal>, weight: float|null}>
     */
    private array $entries = [];

    /**
     * @var list<array{signal: Signal, weight: float|null}>|null
     */
    private ?array $resolved = null;

    public function __construct(private readonly Container $container) {}

    public function register(Signal|string $signal, ?float $weight = null): static
    {
        $this->entries[] = ['signal' => $signal, 'weight' => $weight];
        $this->resolved = null;

        return $this;
    }

    public function all(): array
    {
        return array_map(
            static fn (array $pair): Signal => $pair['signal'],
            $this->resolvedPairs(),
        );
    }

    public function weights(): array
    {
        $weights = [];

        foreach ($this->resolvedPairs() as $pair) {
            if ($pair['weight'] !== null) {
                $weights[$pair['signal']->key()] = $pair['weight'];
            }
        }

        return $weights;
    }

    /**
     * @return list<array{signal: Signal, weight: float|null}>
     */
    private function resolvedPairs(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $pairs = [];

        foreach ($this->entries as $entry) {
            $pairs[] = ['signal' => $this->resolve($entry['signal']), 'weight' => $entry['weight']];
        }

        return $this->resolved = $pairs;
    }

    /**
     * @param  Signal|class-string<Signal>  $signal
     */
    private function resolve(Signal|string $signal): Signal
    {
        if ($signal instanceof Signal) {
            return $signal;
        }

        $made = $this->container->make($signal);

        if (! $made instanceof Signal) {
            throw new InvalidArgumentException("[{$signal}] must implement ".Signal::class.'.');
        }

        return $made;
    }
}
