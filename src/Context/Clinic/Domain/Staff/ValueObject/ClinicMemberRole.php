<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

enum ClinicMemberRole: string
{
    case MANAGER              = 'MANAGER';
    case VETERINARY           = 'VETERINARY';
    case VETERINARY_ASSISTANT = 'VETERINARY_ASSISTANT';
    case RECEPTIONIST         = 'RECEPTIONIST';

    public function canHoldVeterinaryCredentials(): bool
    {
        return match ($this) {
            self::VETERINARY => true,
            default          => false,
        };
    }

    public function canBePractitionerOfRecord(): bool
    {
        return match ($this) {
            self::VETERINARY => true,
            default          => false,
        };
    }

    public function appearsInMedicalAgendaByDefault(): bool
    {
        return match ($this) {
            self::VETERINARY => true,
            default          => false,
        };
    }
}
