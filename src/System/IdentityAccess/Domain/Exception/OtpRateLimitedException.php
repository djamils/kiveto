<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Exception;

/** A new code was asked for before the challenge allows one. */
final class OtpRateLimitedException extends \DomainException
{
    public function __construct(public readonly \DateTimeImmutable $availableAt)
    {
        parent::__construct('Un nouveau code ne peut pas encore être envoyé.');
    }
}
