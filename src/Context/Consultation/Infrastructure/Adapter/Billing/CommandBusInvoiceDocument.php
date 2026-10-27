<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Billing;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFile;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\FindInvoiceForAdmission;
use App\Context\Consultation\Application\Port\ConsultationInvoice;
use App\Context\Consultation\Application\Port\InvoiceDocumentInterface;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Billing's public query (the invoice of a visit, when it may be e-mailed)
 * and command (the PDF it stores at the first send and keeps).
 */
final readonly class CommandBusInvoiceDocument implements InvoiceDocumentInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function invoiceOf(string $clinicId, string $admissionId): ?ConsultationInvoice
    {
        $row = $this->queryBus->ask(new FindInvoiceForAdmission(clinicId: $clinicId, admissionId: $admissionId));

        // A credited (or cancelled) invoice is printed for the trail but never e-mailed.
        return $row instanceof AdmissionInvoiceRow && $row->isSendable() && null !== $row->number
            ? new ConsultationInvoice($row->id, $row->number, $row->totalGrossMinorUnits)
            : null;
    }

    public function sentFile(string $clinicId, string $invoiceId): StoredDocument
    {
        $file = $this->commandBus->dispatch(new EnsureSentInvoiceFile($clinicId, $invoiceId));
        \assert($file instanceof SentDocumentFile);

        return new StoredDocument($file->storageKey, $file->filename);
    }
}
