<?php

declare(strict_types=1);

namespace Cbox\Risk\Contracts;

/**
 * A runtime registry of extra {@see Signal}s to run alongside the ones in
 * `config('risk.signals')`.
 *
 * The config array is the host's list; this registry is the *package* hook — a
 * service provider can contribute a signal from its own `boot()` with no edit to
 * the host's config. That is what lets an optional package (a premium
 * adaptive-risk plugin, a bespoke fraud check) light up new signals just by being
 * installed. Deny-by-default is preserved: an empty registry changes nothing, and
 * only things registered here run.
 *
 * Register a resolved instance, or a `class-string` to be resolved from the
 * container (so the signal's own dependencies are injected):
 *
 *   Risk::signals()->register(MyPremiumSignal::class, weight: 1.5);
 */
interface SignalRegistry
{
    /**
     * Add a signal to the pipeline. Pass an instance, or a class-string that the
     * container will resolve. An optional default weight seeds
     * `config('risk.weights')` for this signal's `key()`; the host's config
     * weight, if any, still overrides it.
     *
     * @param  Signal|class-string<Signal>  $signal
     */
    public function register(Signal|string $signal, ?float $weight = null): static;

    /**
     * All registered signals, resolved.
     *
     * @return list<Signal>
     */
    public function all(): array;

    /**
     * Default weights contributed by registrations (signal `key()` => multiplier),
     * for signals registered with an explicit weight. Host config weights override
     * these.
     *
     * @return array<string, float>
     */
    public function weights(): array;
}
