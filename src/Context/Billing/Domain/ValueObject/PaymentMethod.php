<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

enum PaymentMethod: string
{
    case CASH      = 'CASH';
    case CARD      = 'CARD';
    case TRANSFER  = 'TRANSFER';
    case CHECK     = 'CHECK';
    case INSURANCE = 'INSURANCE';
    case OTHER     = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CASH      => 'Espèces',
            self::CARD      => 'Carte bancaire',
            self::TRANSFER  => 'Virement',
            self::CHECK     => 'Chèque',
            self::INSURANCE => 'Assurance',
            self::OTHER     => 'Autre',
        };
    }

    /**
     * Methods that settle immediately at the desk, offered first at checkout.
     */
    public function isImmediate(): bool
    {
        return self::CASH === $this || self::CARD === $this;
    }
}
