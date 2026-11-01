<?php

declare(strict_types=1);

namespace App\Presentation\Api\Shared\Problem;

/**
 * Builds the problems of an API from its own table of codes, so that shared
 * pieces (the Idempotency-Key decorator) answer with that API's titles.
 */
interface ProblemFactoryInterface
{
    /** 400: the Idempotency-Key header is absent or malformed. */
    public const string IDEMPOTENCY_KEY_MISSING = 'idempotency_key_missing';

    /** 422: the Idempotency-Key was already used for another request. */
    public const string IDEMPOTENCY_KEY_REUSED = 'idempotency_key_reused';

    /**
     * @param array<string, string> $headers
     */
    public function create(string $code, ?string $detail = null, array $headers = [], ?\Throwable $previous = null): ApiProblem;
}
