---
title: Quickstart
description: Protect a route and read the outcome
weight: 2
---

# Quickstart

## Score a route with the middleware

```php
Route::post('/register', RegisterController::class)->middleware('risk:register');
```

The `risk:<action>` middleware scores every request, fires the `RiskAssessed`
event, and stashes the assessment on the request. In **enforce** mode it aborts a
`Reject` with 403; in **monitor** mode it only observes.

Read the assessment downstream to apply friction for the middle bands:

```php
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\ValueObjects\RiskAssessment;

public function __invoke(Request $request)
{
    $risk = $request->attributes->get('risk'); // RiskAssessment|null

    if ($risk?->outcome === Outcome::Challenge) {
        return $this->showCaptcha();
    }

    if ($risk?->outcome === Outcome::StepUp) {
        return $this->requireEmailVerification();
    }

    // ...proceed
}
```

## Add the honeypot to your form

Two hidden inputs the middleware reads (field names are configurable):

```blade
<input type="text" name="nickname" value="" tabindex="-1" autocomplete="off"
       style="position:absolute;left:-9999px" aria-hidden="true">
<input type="hidden" name="rendered_at" value="{{ now()->timestamp }}">
```

`nickname` must stay empty (bots fill it); `rendered_at` catches sub-2-second
submissions. Sign `rendered_at` if you want it tamper-proof.

## Or score anywhere, manually

```php
use Cbox\Risk\Facades\Risk;
use Cbox\Risk\ValueObjects\RiskContext;

$assessment = Risk::assess(RiskContext::fromRequest($request, 'login'));

logger()->info('login risk', ['score' => $assessment->score, 'why' => $assessment->reasons()]);
```
