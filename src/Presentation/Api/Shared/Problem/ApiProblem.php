<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shared\Problem;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * An API error rendered by API Platform as `application/problem+json`.
 *
 * `type` is the bare business code (e.g. `otp_rate_limited`), not a URL: API
 * Platform copies `getType()` verbatim into the problem document. The HTTP
 * status and headers (e.g. `Retry-After` on a 429) come from
 * HttpExceptionInterface, which API Platform reads before anything else.
 */
final class ApiProblem extends \RuntimeException implements ProblemExceptionInterface, HttpExceptionInterface
{
    private readonly string $type;

    private readonly int $status;

    private readonly string $title;

    private readonly ?string $detail;

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $type,
        int $status,
        string $title,
        ?string $detail = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail ?? $title, 0, $previous);

        $this->type    = $type;
        $this->status  = $status;
        $this->title   = $title;
        $this->detail  = $detail;
        $this->headers = $headers;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getInstance(): ?string
    {
        return null;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
