<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * Too many requests (S6e): 10 per 24 hours per account and clinic
 * (`retryAfterSeconds` given), or 3 open change requests on the same pet
 * and field (null: until the clinic answers one). The API answers 429
 * `rate_limited`.
 */
final class PortalRequestRateLimitedException extends \DomainException
{
    public function __construct(public readonly ?int $retryAfterSeconds)
    {
        parent::__construct('Trop de demandes envoyées. Réessayez plus tard.');
    }
}
