<?php

declare(strict_types=1);

namespace Cbox\Risk\Signals;

use Cbox\Risk\Contracts\Signal;
use Cbox\Risk\ValueObjects\RiskContext;
use Cbox\Risk\ValueObjects\SignalResult;

/**
 * Cheap request-shape heuristics: a missing User-Agent, an obvious automation
 * client (curl, python-requests, Go-http-client, headless markers), or the
 * absence of the Accept/Accept-Language headers every real browser sends. None
 * is conclusive alone — they accumulate.
 */
final class UserAgentSignal implements Signal
{
    /** @var list<string> */
    private const BOT_MARKERS = [
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'okhttp', 'libwww-perl', 'httpclient', 'axios/', 'node-fetch',
        'headlesschrome', 'phantomjs', 'scrapy', 'bot', 'spider', 'crawler',
    ];

    public function __construct(
        private readonly float $missingUaPoints = 40.0,
        private readonly float $botUaPoints = 45.0,
        private readonly float $missingHeaderPoints = 15.0,
    ) {}

    public function key(): string
    {
        return 'user_agent';
    }

    public function evaluate(RiskContext $context): ?SignalResult
    {
        $ua = $context->userAgent;

        if ($ua === null || trim($ua) === '') {
            return new SignalResult($this->key(), $this->missingUaPoints, 'request has no User-Agent');
        }

        $lower = strtolower($ua);

        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return new SignalResult($this->key(), $this->botUaPoints, "User-Agent looks automated ({$marker})", ['marker' => $marker]);
            }
        }

        // A real browser always sends Accept and Accept-Language.
        if ($context->header('accept') === null || $context->header('accept-language') === null) {
            return new SignalResult($this->key(), $this->missingHeaderPoints, 'missing Accept/Accept-Language header');
        }

        return null;
    }
}
