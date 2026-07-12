<?php

declare(strict_types=1);

use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\Signals\DisposableEmailSignal;
use Cbox\Risk\Signals\HoneypotSignal;
use Cbox\Risk\Signals\IpReputationSignal;
use Cbox\Risk\Signals\MxRecordSignal;
use Cbox\Risk\Signals\TorExitSignal;
use Cbox\Risk\Signals\UserAgentSignal;
use Cbox\Risk\Signals\VelocitySignal;

return [

    /*
     * Enforcement mode.
     *   'monitor' — score and log, but the host should NOT act on the outcome.
     *               Ship here: calibrate thresholds against real traffic first so
     *               you don't lock out legitimate users on day one.
     *   'enforce' — the host acts on the outcome (challenge / step-up / reject).
     * The scorer always computes a real assessment; this flag tells your app
     * whether to honor it.
     */
    'mode' => env('RISK_MODE', 'monitor'),

    /*
     * The signals to run, in order (FQCN implementing Cbox\Risk\Contracts\Signal).
     * Each is resolved from the container, so its dependencies are injected. All
     * of these ship free-core with no external API and no key.
     */
    'signals' => [
        HoneypotSignal::class,        // honeypot field + submit timing
        UserAgentSignal::class,       // missing/automated UA, missing headers
        DisposableEmailSignal::class, // throwaway email domains (bundled list)
        MxRecordSignal::class,        // email domain can't receive mail
        VelocitySignal::class,        // request rate per IP (hashed) + action
        IpReputationSignal::class,    // stamparm/ipsum via cache (risk:refresh-ipsum)
        TorExitSignal::class,         // Tor exit node (risk:refresh-tor)

        // Opt-in (external API, CC BY-NC data) — uncomment to enable:
        // Cbox\Risk\Signals\StopForumSpamSignal::class,
    ],

    /*
     * Per-signal weight multipliers (signal key() => multiplier). 0 disables a
     * signal's contribution without removing it; >1 amplifies it.
     */
    'weights' => [
        'honeypot' => 1.0,
        'user_agent' => 1.0,
        'email.disposable' => 1.0,
        'email.no_mx' => 1.0,
        'velocity' => 1.0,
        'ip.reputation' => 1.0,
        'ip.tor_exit' => 1.0,
        'ip.stopforumspam' => 1.0,
    ],

    /*
     * Velocity signal: count '<action>' requests per (hashed) IP within `window`
     * seconds; points start once `threshold` is exceeded, scaling with the overage.
     */
    'velocity' => [
        'window' => 300,
        'threshold' => 5,
    ],

    /*
     * IP reputation (ipsum) bands. `level` is how many independent blocklists the
     * IP appears on — the more corroboration, the more points. Two bands: a strong
     * one (many lists) and a medium one. Never dispositive alone (CGNAT/shared IPs).
     */
    'ip_reputation' => [
        'strong_level' => 5,
        'strong_points' => 50,
        'medium_level' => 3,
        'medium_points' => 25,
    ],

    /*
     * Honeypot signal tuning. `min_seconds` is the fastest a genuine human submits;
     * anything quicker scores. A filled honeypot always scores `filled_points`.
     */
    'honeypot_signal' => [
        'min_seconds' => 2,
        'filled_points' => 100,
        'too_fast_points' => 60,
    ],

    /*
     * Score thresholds → outcome. The most severe outcome whose threshold the
     * score meets wins; below all of them is Outcome::Allow. Tune to taste — start
     * permissive. Prefer friction (challenge/step-up) over a hard reject.
     */
    'thresholds' => [
        Outcome::Flag->value => 15,
        Outcome::Challenge->value => 30,
        Outcome::StepUp->value => 60,
        Outcome::Reject->value => 80,
    ],

    /*
     * Allowlists always win — a matching IP or email domain bypasses scoring
     * entirely (returns Allow). Use for your own office ranges and trusted
     * partners to eliminate false positives.
     */
    'allow' => [
        'ips' => [],
        'email_domains' => [],
    ],

    /*
     * Path to the disposable-email-domains list (newline-delimited). Defaults to
     * the bundled starter list; point it at a refreshed copy of
     * amieiro/disposable-email-domains (MIT) for full coverage.
     */
    'disposable_domains_path' => env('RISK_DISPOSABLE_DOMAINS_PATH'),

    /*
     * Field names the `risk` middleware reads for the honeypot signal. `field` is
     * the invisible input a human never fills; `timestamp_field` is the (ideally
     * signed) render time used for submit-timing. Match these to your form.
     */
    'honeypot' => [
        'field' => 'nickname',
        'timestamp_field' => 'rendered_at',
    ],

];
