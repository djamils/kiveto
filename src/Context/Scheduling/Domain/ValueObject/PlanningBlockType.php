<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

enum PlanningBlockType: string
{
    case CONSULTATION = 'consultation';
    case SURGERY      = 'surgery';
    case HEALTH_CHECK = 'health_check';
    case EMERGENCY    = 'emergency';
    case ON_CALL      = 'on_call';
    case LEAVE        = 'leave';
    case TRAINING     = 'training';
    case ADMIN        = 'admin';

    public function acceptsAppointments(): bool
    {
        return match ($this) {
            self::LEAVE, self::TRAINING, self::ADMIN => false,
            default => true,
        };
    }

    public function hasCapacityLimit(): bool
    {
        return match ($this) {
            self::LEAVE, self::TRAINING, self::ADMIN => false,
            default => true,
        };
    }
}
