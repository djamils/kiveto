<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class BookingRequestId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
