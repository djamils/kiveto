<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class ClinicId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
