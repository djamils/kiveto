<?php

declare(strict_types=1);

namespace App\AccessControl\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class UserId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
