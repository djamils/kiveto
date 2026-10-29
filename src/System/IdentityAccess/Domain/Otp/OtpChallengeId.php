<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class OtpChallengeId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
