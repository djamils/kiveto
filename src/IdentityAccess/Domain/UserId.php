<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class UserId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
