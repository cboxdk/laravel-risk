<?php

declare(strict_types=1);

namespace Cbox\Risk\Http;

use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Enums\Outcome;
use Cbox\Risk\Events\RiskAssessed;
use Cbox\Risk\ValueObjects\RiskContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scores an incoming request and attaches the assessment for downstream handling.
 *
 *   Route::post('/register', ...)->middleware('risk:register');
 *
 * It always fires {@see RiskAssessed} (your hook for logging/override) and stashes
 * the assessment on the request as `risk`. In `enforce` mode it aborts a `Reject`
 * with 403; `Challenge`/`StepUp` are left for the app to act on (show a CAPTCHA,
 * require MFA) by reading the stashed assessment. In `monitor` mode it only
 * observes — never blocks — so you can calibrate safely.
 */
final class AssessRequest
{
    public function __construct(private readonly RiskScorer $scorer) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $action = 'request'): Response
    {
        $assessment = $this->scorer->assess($this->context($request, $action));
        $mode = is_string($m = config('risk.mode', 'monitor')) ? $m : 'monitor';

        event(new RiskAssessed($this->context($request, $action), $assessment, $mode));

        $request->attributes->set('risk', $assessment);

        if ($mode === 'enforce' && $assessment->outcome === Outcome::Reject) {
            abort(403, 'This request was blocked.');
        }

        return $next($request);
    }

    private function context(Request $request, string $action): RiskContext
    {
        $honeypotField = is_string($f = config('risk.honeypot.field', 'nickname')) ? $f : 'nickname';
        $timestampField = is_string($t = config('risk.honeypot.timestamp_field', 'rendered_at')) ? $t : 'rendered_at';

        return RiskContext::fromRequest($request, $action, [
            'honeypot' => $request->input($honeypotField),
            'form_rendered_at' => $request->input($timestampField),
        ]);
    }
}
