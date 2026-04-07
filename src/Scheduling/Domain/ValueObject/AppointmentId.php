<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class AppointmentId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
