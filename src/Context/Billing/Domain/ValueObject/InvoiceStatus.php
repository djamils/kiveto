<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

enum InvoiceStatus: string
{
    case DRAFT          = 'DRAFT';
    case ISSUED         = 'ISSUED';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID           = 'PAID';
    case CANCELLED      = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT          => 'Brouillon',
            self::ISSUED         => 'Impayée',
            self::PARTIALLY_PAID => 'Partielle',
            self::PAID           => 'Payée',
            self::CANCELLED      => 'Annulée',
        };
    }

    /**
     * Whether the invoice still expects money.
     */
    public function isOutstanding(): bool
    {
        return self::ISSUED === $this || self::PARTIALLY_PAID === $this;
    }

    public function isEditable(): bool
    {
        return self::DRAFT === $this;
    }
}
