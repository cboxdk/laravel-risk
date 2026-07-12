---
title: Cookbook
description: Task-oriented recipes
weight: 4
---

# Cookbook

## Protect registration

```php
Route::post('/register', RegisterController::class)->middleware('risk:register');
```

Add the honeypot inputs to the form (see [Quickstart](getting-started/quickstart.md))
and branch on the stashed assessment for CAPTCHA/step-up.

## Log every decision (the audit trail)

```php
// A listener on the RiskAssessed hook.
class LogRiskDecision
{
    public function handle(RiskAssessed $event): void
    {
        Log::channel('risk')->info('assessed', [
            'action'  => $event->context->action,
            'ip_hash' => hash_hmac('sha256', (string) $event->context->ip, config('app.key')),
            'score'   => $event->assessment->score,
            'outcome' => $event->assessment->outcome->value,
            'reasons' => $event->assessment->reasons(),
            'enforced'=> $event->enforcing(),
        ]);
    }
}
```

Note the **hashed IP** — see [Security](security.md) on data minimization.

## Route high risk to human review instead of auto-blocking

```php
public function handle(RiskAssessed $event): void
{
    if ($event->assessment->atLeast(Outcome::StepUp)) {
        ReviewQueue::push($event->context, $event->assessment); // human decides
    }
}
```

This keeps a person in the loop for significant decisions (GDPR Art. 22).

## Calibrate before you enforce (the tuning workflow)

1. **Ship in `monitor` mode** (the default). Nothing is blocked yet.
2. **Log every decision** with its reasons (see the audit-trail recipe above).
3. **Watch for a week.** Look at the score distribution and *which signals fire on
   your real users*. A signal that fires constantly on legitimate traffic (e.g.
   `user_agent` because your app has many API clients) should be down-weighted.
4. **Adjust weights and thresholds**, still in monitor mode, until the scores you'd
   act on line up with the requests you'd actually want to challenge.
5. **Flip `RISK_MODE=enforce`.** Start by enforcing only the top band (`Reject`),
   then enable `Challenge`/`StepUp` handling as you gain confidence.

```php
'weights' => [
    'ip.reputation' => 1.5,     // trust ipsum more
    'user_agent'    => 0.5,     // you get lots of legit odd UAs
],
'thresholds' => [
    Outcome::Challenge->value => 40, // raise to reduce CAPTCHAs
],
```

## Combine signals — no single one should block

The model is additive on purpose: one weak signal shouldn't lock anyone out, but
several together should. Worked examples with default points:

| Request | Signals (points) | Total | Outcome |
|---------|------------------|-------|---------|
| Tor user, clean otherwise | `ip.tor_exit` (20) | 20 | Flag |
| Disposable email on a datacenter IP | `email.disposable` (40) + `ip.reputation` medium (25) | 65 | StepUp |
| Bot: filled honeypot | `honeypot` (100) | 100 | Reject |
| Made-up throwaway address | `email.disposable` (40) + `email.no_mx` (50) | 90 | Reject |
| Scripted signup | `user_agent` curl (45) + `velocity` (30) | 75 | StepUp |

Tune the weights so the *combinations you care about* land in the band you want —
that's the whole job.

## Weight an IP by how many lists it's on

`ip.reputation` already bands by ipsum level (how many blocklists corroborate the
IP). Make the strong band count for more:

```php
'ip_reputation' => [
    'strong_level'  => 5,   // on >= 5 lists
    'strong_points' => 70,  // ...counts a lot (was 50)
    'medium_level'  => 3,
    'medium_points' => 20,
],
```

## Allowlist your office and partners

```php
'allow' => [
    'ips' => ['203.0.113.10', '198.51.100.0'],
    'email_domains' => ['your-company.com', 'trusted-partner.com'],
],
```

## Score a non-HTTP flow

```php
$assessment = Risk::assess(new RiskContext(
    action: 'import',
    ip: $job->sourceIp,
    email: $row['email'],
));
```
