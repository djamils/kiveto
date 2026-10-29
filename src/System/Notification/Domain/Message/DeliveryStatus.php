<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Where a message stands. The rank orders the statuses: a lower rank never
 * replaces a higher one, and nothing replaces a final status (rank 2).
 * DEFERRED is a soft bounce — the provider keeps trying — and is never final.
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

    public function rank(): int
    {
        return match ($this) {
            self::QUEUED => 0,
            self::SENT, self::DEFERRED => 1,
            default => 2,
        };
    }

    public function isFinal(): bool
    {
        return 2 === $this->rank();
    }

    /** French label shown in the journals (readable without colour). */
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
