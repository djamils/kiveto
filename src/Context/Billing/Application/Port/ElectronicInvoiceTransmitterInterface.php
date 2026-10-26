<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;

/**
 * Hands issued professional documents to the electronic-invoicing context,
 * which transmits them to the clinic's approved platform.
 *
 * The document is frozen when it is issued — lines, amounts, the seller's
 * identity and the amount already paid (BT-113) as they are at that moment —
 * and transmitted later exactly as frozen: nothing is read again at send
 * time. Transmitting is idempotent: the same document twice is sent once.
 */
interface ElectronicInvoiceTransmitterInterface
{
    /**
     * @return array<string, mixed> the neutral document, opaque to Billing
     */
    public function freezeInvoice(Invoice $invoice, SellerIdentity $seller): array;

    /**
     * @return array<string, mixed> the neutral document, opaque to Billing
     */
    public function freezeCreditNote(CreditNote $creditNote, SellerIdentity $seller): array;

    /**
     * @param array<string, mixed> $document as frozen at issue
     */
    public function transmit(string $clinicId, array $document): void;

    /**
     * Where electronic invoicing has the document, or null when it never
     * received it.
     */
    public function status(string $documentId): ?TransmissionStatusUpdate;
}
