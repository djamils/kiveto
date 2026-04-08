<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

final readonly class PractitionerAssignee
{
    public function __construct(
        private UserId $userId,
    ) {
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function equals(self $other): bool
    {
        return $this->userId->equals($other->userId);
    }
}
