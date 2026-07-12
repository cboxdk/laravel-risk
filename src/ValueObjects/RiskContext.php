<?php

declare(strict_types=1);

namespace Cbox\Risk\ValueObjects;

use Illuminate\Http\Request;

/**
 * The immutable input to a risk assessment: everything the signals score against.
 * `action` names what is being attempted (e.g. `register`, `login`,
 * `form.contact`) so rules and weights can differ per action.
 */
final readonly class RiskContext
{
    /**
     * @param  array<string, string>  $headers  lowercased header name => value
     * @param  array<string, mixed>  $attributes  arbitrary extra signals (form timing, honeypot, fingerprint, …)
     */
    public function __construct(
        public string $action,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $email = null,
        public array $headers = [],
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromRequest(Request $request, string $action, array $attributes = []): self
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = (string) ($values[0] ?? '');
        }

        $email = $request->input('email');

        return new self(
            action: $action,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            email: is_string($email) ? $email : null,
            headers: $headers,
            attributes: $attributes,
        );
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
