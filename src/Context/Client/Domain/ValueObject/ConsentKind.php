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

    /**
     * The kinds the owner may withdraw: withdrawing one stops the sending.
     * The other kinds are sent anyway, their consent only picks the channel.
     * An archived client receives none of the optional kinds.
     */
    public const array OPTIONAL = [self::MARKETING, self::APPOINTMENT_REMINDERS, self::VACCINATION_REMINDERS];

    public function isOptional(): bool
    {
        return \in_array($this, self::OPTIONAL, true);
    }

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
