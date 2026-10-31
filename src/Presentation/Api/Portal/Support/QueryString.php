<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * The query string of the current API Platform request, read as scalars:
 * an array or an empty value reads as absent. The operation's
 * QueryParameter constraints validated it already.
 */
final readonly class QueryString
{
    private function __construct(private Request $request)
    {
    }

    /**
     * @param array<string, mixed> $context the provider context
     */
    public static function of(array $context): self
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        return new self($request);
    }

    public function string(string $name): ?string
    {
        $value = $this->request->query->all()[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function int(string $name): ?int
    {
        $value = $this->string($name);

        return null !== $value && 1 === preg_match('/^-?\d{1,9}$/', $value) ? (int) $value : null;
    }
}
