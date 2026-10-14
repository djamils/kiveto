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

    /** Issued, then entirely cancelled out by credit notes: it stays in the register with its number. */
    case CREDITED = 'CREDITED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT          => 'Brouillon',
            self::ISSUED         => 'Impayée',
            self::PARTIALLY_PAID => 'Partielle',
            self::PAID           => 'Payée',
            self::CANCELLED      => 'Annulée',
            self::CREDITED       => 'Annulée par avoir',
        };
    }

    /**
     * Whether the invoice still expects money.
     */
    public function isOutstanding(): bool
    {
        return self::ISSUED === $this || self::PARTIALLY_PAID === $this;
    }

    /**
     * Whether a credit note may still be issued against it: it has a number,
     * and something of it is left to credit.
     */
    public function isCreditable(): bool
    {
        return self::ISSUED === $this || self::PARTIALLY_PAID === $this || self::PAID === $this;
    }

    public function isEditable(): bool
    {
        return self::DRAFT === $this;
    }
}
