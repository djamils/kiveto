<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ConsentKind: string
{
    case APPOINTMENT_REMINDERS = 'appointment_reminders';
    case VACCINATION_REMINDERS = 'vaccination_reminders';
    case CLINICAL_DOCUMENTS    = 'clinical_documents';
    case BILLING               = 'billing';
    case MARKETING             = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::APPOINTMENT_REMINDERS => 'Rappels de rendez-vous',
            self::VACCINATION_REMINDERS => 'Rappels de vaccination',
            self::CLINICAL_DOCUMENTS    => 'Comptes rendus et ordonnances',
            self::BILLING               => 'Factures et relances de règlement',
            self::MARKETING             => 'Actualités et offres',
        };
    }
}
