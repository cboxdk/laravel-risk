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

## Tune weights and thresholds

Start in monitor mode, watch the logged scores for a week, then adjust:

```php
'weights' => [
    'ip.reputation' => 1.5,     // trust it more
    'user_agent'    => 0.5,     // you get lots of legit odd UAs
],
'thresholds' => [
    Outcome::Challenge->value => 40, // raise to reduce CAPTCHAs
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
