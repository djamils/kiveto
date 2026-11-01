<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class BookingMotifId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
