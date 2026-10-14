<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class WeightRecordId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
