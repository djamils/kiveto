<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

enum PlanningBlockType: string
{
    case CONSULTATION = 'consultation';
    case CHIRURGIE    = 'chirurgie';
    case BILAN        = 'bilan';
    case URGENCE      = 'urgence';
    case GARDE        = 'garde';
    case CONGE        = 'conge';
    case FORMATION    = 'formation';
    case ADMIN        = 'admin';

    public function acceptsAppointments(): bool
    {
        return match ($this) {
            self::CONGE, self::FORMATION, self::ADMIN => false,
            default => true,
        };
    }

    public function hasCapacityLimit(): bool
    {
        return match ($this) {
            self::CONGE, self::FORMATION, self::ADMIN => false,
            default => true,
        };
    }
}
