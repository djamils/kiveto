<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Why a message is sent. Criticality and sender are fixed per purpose, never
 * chosen by the requester.
 */
enum MessagePurpose: string
{
    case APPOINTMENT_CONFIRMATION    = 'APPOINTMENT_CONFIRMATION';
    case APPOINTMENT_CHANGE          = 'APPOINTMENT_CHANGE';
    case APPOINTMENT_CANCELLATION    = 'APPOINTMENT_CANCELLATION';
    case APPOINTMENT_REMINDER        = 'APPOINTMENT_REMINDER';
    case APPOINTMENT_DECLINED        = 'APPOINTMENT_DECLINED';
    case INVOICE_SENT                = 'INVOICE_SENT';
    case INVOICE_REMINDER            = 'INVOICE_REMINDER';
    case CONSULTATION_DOCUMENTS      = 'CONSULTATION_DOCUMENTS';
    case STAFF_INVITATION            = 'STAFF_INVITATION';
    case PASSWORD_RESET              = 'PASSWORD_RESET';
    case TEST                        = 'TEST';
    case PORTAL_OTP                  = 'PORTAL_OTP';
    case PORTAL_INVITATION           = 'PORTAL_INVITATION';
    case PORTAL_EMAIL_VERIFICATION   = 'PORTAL_EMAIL_VERIFICATION';
    case PORTAL_EMAIL_CHANGED_NOTICE = 'PORTAL_EMAIL_CHANGED_NOTICE';
    case PORTAL_DATA_EXPORT_READY    = 'PORTAL_DATA_EXPORT_READY';
    case PORTAL_REQUEST_RESOLVED     = 'PORTAL_REQUEST_RESOLVED';
    case PORTAL_BOOKING_DECLINED     = 'PORTAL_BOOKING_DECLINED';

    /**
     * Only reminders, the answer to a Portal request and the decline of a
     * new client's request may be held back by
     * the SMS overage cap; e-mail-only purposes are critical too (the cap
     * concerns SMS alone).
     */
    public function criticality(): Criticality
    {
        return match ($this) {
            self::APPOINTMENT_REMINDER, self::INVOICE_REMINDER, self::PORTAL_REQUEST_RESOLVED, self::PORTAL_BOOKING_DECLINED => Criticality::NON_CRITICAL,
            default => Criticality::CRITICAL,
        };
    }

    /**
     * Account, Portal and test messages are sent by Kiveto itself, not on
     * behalf of a clinic, and need no clinic messaging profile.
     */
    public function isSentByKiveto(): bool
    {
        return \in_array($this, [self::STAFF_INVITATION, self::PASSWORD_RESET, self::TEST, self::PORTAL_OTP, self::PORTAL_INVITATION, self::PORTAL_EMAIL_VERIFICATION, self::PORTAL_EMAIL_CHANGED_NOTICE, self::PORTAL_DATA_EXPORT_READY, self::PORTAL_BOOKING_DECLINED], true);
    }

    /**
     * Kiveto's own messages to an owner account (codes and account notices),
     * erased when the account is deleted. The Portal invitation is a clinic's
     * message to its client, journaled in the client file: it is kept. The
     * decline of a new client's request went to the account phone, not to a
     * client file: it is erased.
     */
    public function isPortalAccountMessage(): bool
    {
        return \in_array($this, [self::PORTAL_OTP, self::PORTAL_EMAIL_VERIFICATION, self::PORTAL_EMAIL_CHANGED_NOTICE, self::PORTAL_DATA_EXPORT_READY, self::PORTAL_BOOKING_DECLINED], true);
    }

    /**
     * How long the one-time link the message carries can be used (the
     * IdentityAccess token lifetimes, the Portal one-time code and invitation),
     * from when the message is requested; null when it carries none.
     */
    public function linkValidity(): ?\DateInterval
    {
        return match ($this) {
            self::STAFF_INVITATION          => new \DateInterval('PT72H'),
            self::PASSWORD_RESET            => new \DateInterval('PT30M'),
            self::PORTAL_OTP                => new \DateInterval('PT10M'),
            self::PORTAL_INVITATION         => new \DateInterval('P7D'),
            self::PORTAL_EMAIL_VERIFICATION => new \DateInterval('PT10M'),
            default                         => null,
        };
    }

    /** French label, used as the subject line of an SMS in the journals. */
    public function label(): string
    {
        return match ($this) {
            self::APPOINTMENT_CONFIRMATION    => 'Confirmation de rendez-vous',
            self::APPOINTMENT_CHANGE          => 'Modification de rendez-vous',
            self::APPOINTMENT_CANCELLATION    => 'Annulation de rendez-vous',
            self::APPOINTMENT_REMINDER        => 'Rappel de rendez-vous',
            self::APPOINTMENT_DECLINED        => 'Demande de RDV non confirmée',
            self::INVOICE_SENT                => 'Envoi de facture',
            self::INVOICE_REMINDER            => "Relance d'impayé",
            self::CONSULTATION_DOCUMENTS      => 'Documents de consultation',
            self::STAFF_INVITATION            => "Invitation d'un membre",
            self::PASSWORD_RESET              => 'Réinitialisation du mot de passe',
            self::TEST                        => 'Message de test Kiveto',
            self::PORTAL_OTP                  => 'Code de connexion Portal',
            self::PORTAL_INVITATION           => 'Invitation au Portal',
            self::PORTAL_EMAIL_VERIFICATION   => 'Vérification de l\'adresse e-mail Portal',
            self::PORTAL_EMAIL_CHANGED_NOTICE => 'Adresse e-mail Portal modifiée',
            self::PORTAL_DATA_EXPORT_READY    => 'Export de données Portal prêt',
            self::PORTAL_REQUEST_RESOLVED     => 'Réponse à une demande Portal',
            self::PORTAL_BOOKING_DECLINED     => 'Demande de RDV Portal non confirmée',
        };
    }
}
