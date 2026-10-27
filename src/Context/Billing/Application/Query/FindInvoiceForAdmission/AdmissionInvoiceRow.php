<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\FindInvoiceForAdmission;

use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClientHandler;

final readonly class AdmissionInvoiceRow
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public int $totalGrossMinorUnits,
        public string $currency,
    ) {
    }

    /**
     * A draft has no number and no legal value; a cancelled invoice is kept for
     * the trail but is not handed to the client.
     */
    public function isPrintable(): bool
    {
        return null !== $this->number && 'CANCELLED' !== $this->status;
    }

    /**
     * What may be e-mailed to the client, as « Envoyer » allows it: issued,
     * partly paid or paid — never a draft, a cancelled or a credited one.
     */
    public function isSendable(): bool
    {
        return null !== $this->number && SendInvoiceToClientHandler::isSendable($this->status);
    }
}
