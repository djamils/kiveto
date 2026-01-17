<?php

declare(strict_types=1);

namespace App\Client\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class ClientId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
