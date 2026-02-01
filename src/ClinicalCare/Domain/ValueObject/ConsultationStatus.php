<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

enum ConsultationStatus: string
{
    case OPEN   = 'OPEN';
    case CLOSED = 'CLOSED';

    public function isOpen(): bool
    {
        return self::OPEN === $this;
    }

    public function isClosed(): bool
    {
        return self::CLOSED === $this;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::OPEN   => self::CLOSED === $newStatus,
            self::CLOSED => false, // No transitions from CLOSED
        };
    }
}
