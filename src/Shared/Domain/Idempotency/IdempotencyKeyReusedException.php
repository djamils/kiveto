<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

/**
 * The idempotency key was already used by the same principal for a different
 * request (another fingerprint) within the retention window.
 */
final class IdempotencyKeyReusedException extends \DomainException
{
    public function __construct(IdempotencyKey $key)
    {
        parent::__construct(\sprintf('The idempotency key "%s" was already used for a different request.', $key->toString()));
    }
}
