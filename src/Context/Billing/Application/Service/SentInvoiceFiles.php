<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Service;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\InvoicePdfRendererInterface;
use App\Context\Billing\Application\Port\SentDocumentFileStoreInterface;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * The invoice PDF attached to its e-mails, stored once at the first send
 * (AC 25b). Network first, writes last: the PDF is rendered (Gotenberg) and
 * written under its deterministic key before the row is inserted, so no row
 * lock is held during the call. Two concurrent first sends write the same
 * file; the second insert is ignored and both return the recorded row.
 */
final readonly class SentInvoiceFiles
{
    public function __construct(
        private SentDocumentFileStoreInterface $store,
        private InvoicePdfRendererInterface $renderer,
        private FileStorageInterface $storage,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException when the invoice is unknown to this clinic
     */
    public function ensure(string $clinicId, string $invoiceId): SentDocumentFile
    {
        $clinicId  = ClinicId::fromString($clinicId)->toString();
        $invoiceId = InvoiceId::fromString($invoiceId)->toString();

        $existing = $this->store->find($clinicId, $invoiceId);

        if (null !== $existing) {
            return $existing;
        }

        $document = $this->renderer->invoice($clinicId, $invoiceId);
        $file     = new SentDocumentFile('billing/sent/invoice/' . $invoiceId . '.pdf', self::filename($document->filename));

        $this->storage->storeAt($file->storageKey, $document->content);

        return $this->store->record($clinicId, SentDocumentKind::INVOICE, $invoiceId, $file, $this->clock->now());
    }

    /** An attachment name every mail client reads: « FAC-2026-000012.pdf ». */
    private static function filename(string $raw): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $raw);

        return '' !== mb_trim($name, '-.') ? $name : 'facture.pdf';
    }
}
