<?php

declare(strict_types=1);

namespace App\Tests\Support\Billing;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;

/**
 * Freezes a document as its id, number, amount paid and seller; remembers
 * what it transmits; answers the statuses it is given — and throws for the
 * documents listed in $failing.
 */
final class RecordingTransmitter implements ElectronicInvoiceTransmitterInterface
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $transmitted = [];

    /** @var array<string, TransmissionStatusUpdate> */
    public array $statuses = [];

    /** @var list<string> */
    public array $failing = [];

    public function freezeInvoice(Invoice $invoice, SellerIdentity $seller): array
    {
        return ['documentId' => $invoice->id()->toString(), 'number' => $invoice->number()?->toString(), 'paid' => $invoice->amountPaid()->minorUnits(), 'seller' => $seller->legalName];
    }

    public function freezeCreditNote(CreditNote $creditNote, SellerIdentity $seller): array
    {
        return ['documentId' => $creditNote->id()->toString(), 'number' => $creditNote->number()->toString(), 'seller' => $seller->legalName];
    }

    public function transmit(string $clinicId, array $document): void
    {
        $this->transmitted[] = [$clinicId, $document];
    }

    public function status(string $documentId): ?TransmissionStatusUpdate
    {
        if (\in_array($documentId, $this->failing, true)) {
            throw new \RuntimeException('Electronic invoicing is unavailable.');
        }

        return $this->statuses[$documentId] ?? null;
    }
}
