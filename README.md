# Cbox Risk

An **explainable, config-driven request risk-scoring pipeline** for Laravel. It
weights independent signals — IP reputation, disposable email, bot user-agents,
honeypot/submit-timing — into a score, and maps that score to a graduated outcome:
**allow, flag, challenge, step-up, or reject**. Every decision comes with the
reasons behind it, so you can tune it, explain it, and defend it.

```php
use Cbox\Risk\Facades\Risk;
use Cbox\Risk\ValueObjects\RiskContext;

$assessment = Risk::assess(RiskContext::fromRequest($request, action: 'register', attributes: [
    'honeypot' => $request->input('nickname'),        // an invisible field
    'form_rendered_at' => $request->integer('rendered_at'),
]));

if ($assessment->atLeast(Outcome::Challenge)) {
    // show a CAPTCHA, require step-up, or reject — your call, per the outcome
}
```

## Why this exists

Every auth/anti-abuse tool in the Laravel ecosystem is either a **single-signal
hard blocker** (one IP list, one honeypot) or an **opaque paid cloud** (Akismet,
CleanTalk) whose verdicts you can't explain. Neither fits an identity platform,
where a wrong block locks a real user out and a regulator may ask *why* you
made an automated decision. Cbox Risk is the missing middle: **many signals, a
transparent weighted score, graduated friction instead of a binary block, and a
full reasons breakdown on every assessment** — self-hosted, free-core, no data
leaving your server.

## Free-core signals (ship working, zero paid deps, clean licenses)

| Signal | What it catches | Source |
|--------|-----------------|--------|
| **Honeypot + timing** | Bots that fill hidden fields or submit in <2s | built-in (spatie technique) |
| **User-Agent** | `curl`/`python-requests`/headless clients, missing Accept headers | built-in |
| **Disposable email** | Throwaway signup addresses | bundled list (refresh from [amieiro, MIT](https://github.com/amieiro/disposable-email-domains)) |
| **MX record** | Email domains that can't receive mail (undeliverable = fake) | DNS |
| **Velocity** | Too many requests from one IP (stored HMAC-only) | your cache |
| **IP reputation** | Addresses on many blocklists | [stamparm/ipsum](https://github.com/stamparm/ipsum) (Unlicense) via `risk:refresh-ipsum` |
| **Tor exit** | Requests via a Tor exit node (moderate) | [official list](https://check.torproject.org/torbulkexitlist) via `risk:refresh-tor` |

**Opt-in signal** (ships, enable in config): **StopForumSpam** — cached, short
timeout, fail-open (data is CC BY-NC, fine for self-hosters).

**Opt-in drivers** (bring your own key/implementation): AbuseIPDB, Spamhaus DQS,
Project Honey Pot, IPQualityScore, MaxMind GeoLite2, HIBP breach API — each a
`Signal` or a bound `IpReputation`/`DisposableDomains` provider. **No hard
dependency on any paid API**; an unconfigured signal contributes 0, never an error.

## Install

```bash
composer require cboxdk/laravel-risk
php artisan vendor:publish --tag=risk-config
```

Schedule the IP-reputation refresh (daily):

```php
// routes/console.php
Schedule::command('risk:refresh-ipsum')->daily();
```

## Monitor first, enforce later

The package **ships in `monitor` mode**: it scores and you log, but you do **not**
act on the outcome yet. Calibrate the thresholds against your real traffic — then
flip `RISK_MODE=enforce`. Anti-abuse tools that block on install cause outages;
this one refuses to.

## The scoring model

Weighted-additive and deliberately **not** machine learning: `score = Σ (signal
points × weight)`, mapped to the most severe outcome band it reaches. It's
explainable by construction — `$assessment->reasons()` tells you exactly which
signals fired and why. See [`docs/core-concepts/architecture.md`](docs/core-concepts/architecture.md).

Defaults (all configurable): Flag ≥15, Challenge ≥30, Step-up ≥60, Reject ≥80.
Allowlisted IPs and email domains bypass scoring entirely.

## Outcomes

| Outcome | Meaning | Typical action |
|---------|---------|----------------|
| `Allow` | Looks fine | proceed |
| `Flag` | Slightly odd | proceed, but log for review |
| `Challenge` | Suspicious | CAPTCHA / proof-of-work |
| `StepUp` | Risky | extra auth factor / email verification |
| `Reject` | Almost certainly abuse | refuse (or hold for review) |

## Custom signals

Implement `Cbox\Risk\Contracts\Signal` and add it to `config('risk.signals')`:

```php
final class GeoVelocitySignal implements Signal
{
    public function key(): string { return 'geo.velocity'; }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        // return new SignalResult('geo.velocity', 30, 'impossible travel') or null
    }
}
```

## Privacy & GDPR

IP addresses and fingerprints are personal data. Fraud prevention is a recognized
legitimate interest (GDPR Recital 47), but you owe users **explainability and human
review** under **Article 22** — which is exactly why this scorer is transparent, not
a black box. See [`docs/security/index.md`](docs/security/_index.md) for the Art. 22 guidance,
data-minimization defaults, and retention advice.

## License

MIT © Cbox. Security policy: [SECURITY.md](SECURITY.md).
