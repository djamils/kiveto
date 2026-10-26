<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Where an automated message stands, as the Notification context reports
 * it (same values). The French labels are readable without colour.
 */
enum DeliveryStatus: string
{
    case QUEUED      = 'QUEUED';
    case SENT        = 'SENT';
    case DEFERRED    = 'DEFERRED';
    case DELIVERED   = 'DELIVERED';
    case BOUNCED     = 'BOUNCED';
    case REJECTED    = 'REJECTED';
    case FAILED      = 'FAILED';
    case BLOCKED_CAP = 'BLOCKED_CAP';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED      => 'En cours',
            self::SENT        => 'Envoyé',
            self::DEFERRED    => 'Différé',
            self::DELIVERED   => 'Délivré',
            self::BOUNCED     => 'Non délivré',
            self::REJECTED    => 'Rejeté',
            self::FAILED      => 'Échec',
            self::BLOCKED_CAP => 'Bloqué (plafond)',
        };
    }
}
