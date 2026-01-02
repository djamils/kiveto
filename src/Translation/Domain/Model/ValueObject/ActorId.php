<?php

declare(strict_types=1);

namespace App\Translation\Domain\Model\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class ActorId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
