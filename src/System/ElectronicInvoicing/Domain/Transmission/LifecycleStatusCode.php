<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * The French lifecycle statuses of an invoice (XP Z12-012, Annex A), as the
 * platforms report them in CDAR messages (« ProcessConditionCode »).
 */
enum LifecycleStatusCode: int
{
    case DEPOSITED                = 200;
    case ISSUED                   = 201;
    case RECEIVED                 = 202;
    case MADE_AVAILABLE           = 203;
    case TAKEN_IN_CHARGE          = 204;
    case APPROVED                 = 205;
    case PARTIALLY_APPROVED       = 206;
    case DISPUTED                 = 207;
    case SUSPENDED                = 208;
    case COMPLETED                = 209;
    case REFUSED                  = 210;
    case PAYMENT_TRANSMITTED      = 211;
    case COLLECTED                = 212;
    case REJECTED                 = 213;
    case APPROVED_FOR_PAYMENT     = 214;
    case CANCELLED                = 220;
    case ROUTING_ERROR            = 221;
    case DIRECT_PAYMENT_REQUESTED = 224;
    case FACTORED                 = 225;
    case FACTORED_CONFIDENTIAL    = 226;
    case PAYABLE_ACCOUNT_CHANGED  = 227;
    case NOT_FACTORED             = 228;
    case ADMISSIBLE               = 500;
    case INADMISSIBLE             = 501;

    public function label(): string
    {
        return match ($this) {
            self::DEPOSITED                => 'Déposée',
            self::ISSUED                   => 'Émise',
            self::RECEIVED                 => 'Reçue',
            self::MADE_AVAILABLE           => 'Mise à disposition',
            self::TAKEN_IN_CHARGE          => 'Prise en charge',
            self::APPROVED                 => 'Approuvée',
            self::PARTIALLY_APPROVED       => 'Approuvée partiellement',
            self::DISPUTED                 => 'En litige',
            self::SUSPENDED                => 'Suspendue',
            self::COMPLETED                => 'Complétée',
            self::REFUSED                  => 'Refusée',
            self::PAYMENT_TRANSMITTED      => 'Paiement transmis',
            self::COLLECTED                => 'Encaissée',
            self::REJECTED                 => 'Rejetée',
            self::APPROVED_FOR_PAYMENT     => 'Visée',
            self::CANCELLED                => 'Annulée',
            self::ROUTING_ERROR            => 'Erreur de routage',
            self::DIRECT_PAYMENT_REQUESTED => 'Demande de paiement direct',
            self::FACTORED                 => 'Affacturée',
            self::FACTORED_CONFIDENTIAL    => 'Affacturée confidentiel',
            self::PAYABLE_ACCOUNT_CHANGED  => 'Changement de compte à payer',
            self::NOT_FACTORED             => 'Non affacturée',
            self::ADMISSIBLE               => 'Recevable',
            self::INADMISSIBLE             => 'Irrecevable',
        };
    }

    public function requiresAction(): bool
    {
        return \in_array($this, [self::DISPUTED, self::SUSPENDED, self::REFUSED, self::REJECTED, self::ROUTING_ERROR, self::INADMISSIBLE], true);
    }
}
