<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

enum PrescriptionStatus: string
{
    case DRAFT     = 'DRAFT';
    case ISSUED    = 'ISSUED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Brouillon',
            self::ISSUED    => 'Émise',
            self::CANCELLED => 'Annulée',
        };
    }

    public function isEditable(): bool
    {
        return self::DRAFT === $this;
    }
}
