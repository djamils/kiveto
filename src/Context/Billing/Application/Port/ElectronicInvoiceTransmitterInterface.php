<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;

/**
 * Hands an issued professional document to the electronic-invoicing context,
 * which transmits it to the clinic's approved platform. Idempotent: the same
 * document requested twice is transmitted once.
 */
interface ElectronicInvoiceTransmitterInterface
{
    public function transmitInvoice(Invoice $invoice, SellerIdentity $seller): void;

    public function transmitCreditNote(CreditNote $creditNote, SellerIdentity $seller): void;
}
