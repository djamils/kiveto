<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

final class LocationStatus
{
    private LocationStatusValue $value;

    private \DateTimeImmutable $enteredAt;

    public function __construct(LocationStatusValue $value, \DateTimeImmutable $enteredAt)
    {
        $this->value     = $value;
        $this->enteredAt = $enteredAt;
    }

    public function value(): LocationStatusValue
    {
        return $this->value;
    }

    public function enteredAt(): \DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value
            && $this->enteredAt->getTimestamp() === $other->enteredAt->getTimestamp();
    }
}
