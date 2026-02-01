<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

enum ConsultationStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::OPEN => $newStatus === self::CLOSED,
            self::CLOSED => false, // No transitions from CLOSED
        };
    }
}
