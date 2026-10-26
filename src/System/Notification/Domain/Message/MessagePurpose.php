<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Why a message is sent. Criticality and sender are fixed per purpose, never
 * chosen by the requester.
 */
enum MessagePurpose: string
{
    case APPOINTMENT_CONFIRMATION = 'APPOINTMENT_CONFIRMATION';
    case APPOINTMENT_CHANGE       = 'APPOINTMENT_CHANGE';
    case APPOINTMENT_CANCELLATION = 'APPOINTMENT_CANCELLATION';
    case APPOINTMENT_REMINDER     = 'APPOINTMENT_REMINDER';
    case INVOICE_SENT             = 'INVOICE_SENT';
    case INVOICE_REMINDER         = 'INVOICE_REMINDER';
    case CONSULTATION_DOCUMENTS   = 'CONSULTATION_DOCUMENTS';
    case STAFF_INVITATION         = 'STAFF_INVITATION';
    case PASSWORD_RESET           = 'PASSWORD_RESET';
    case TEST                     = 'TEST';

    /**
     * Only reminders may be held back by the SMS overage cap; e-mail-only
     * purposes are critical too (the cap concerns SMS alone).
     */
    public function criticality(): Criticality
    {
        return match ($this) {
            self::APPOINTMENT_REMINDER, self::INVOICE_REMINDER => Criticality::NON_CRITICAL,
            default => Criticality::CRITICAL,
        };
    }

    /**
     * Account and test messages are sent by Kiveto itself, not on behalf of a
     * clinic, and need no clinic messaging profile.
     */
    public function isSentByKiveto(): bool
    {
        return \in_array($this, [self::STAFF_INVITATION, self::PASSWORD_RESET, self::TEST], true);
    }

    /** French label, used as the subject line of an SMS in the journals. */
    public function label(): string
    {
        return match ($this) {
            self::APPOINTMENT_CONFIRMATION => 'Confirmation de rendez-vous',
            self::APPOINTMENT_CHANGE       => 'Modification de rendez-vous',
            self::APPOINTMENT_CANCELLATION => 'Annulation de rendez-vous',
            self::APPOINTMENT_REMINDER     => 'Rappel de rendez-vous',
            self::INVOICE_SENT             => 'Envoi de facture',
            self::INVOICE_REMINDER         => "Relance d'impayé",
            self::CONSULTATION_DOCUMENTS   => 'Documents de consultation',
            self::STAFF_INVITATION         => "Invitation d'un membre",
            self::PASSWORD_RESET           => 'Réinitialisation du mot de passe',
            self::TEST                     => 'Message de test Kiveto',
        };
    }
}
