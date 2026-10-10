<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum CommunicationDirection: string
{
    case INBOUND  = 'inbound';
    case OUTBOUND = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND  => 'Reçu',
            self::OUTBOUND => 'Émis',
        };
    }
}
