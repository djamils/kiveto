<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class OwnerId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
