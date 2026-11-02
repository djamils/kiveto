<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\InvoiceDocumentsInterface;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;

/**
 * The live state check of the documents list, for every other read that
 * must agree with it (the file route, the unread badge): an invoice without
 * payment status or a prescription no longer issued (cancelled before its
 * withdrawal was consumed) is left out. One batch per type.
 */
final readonly class ListableSources
{
    public function __construct(
        private InvoiceDocumentsInterface $invoices,
        private PrescriptionDocumentsInterface $prescriptions,
    ) {
    }

    /**
     * @param list<ListedDocument> $rows
     *
     * @return list<ListedDocument> the rows still listable, in their order
     */
    public function keep(string $clinicId, array $rows): array
    {
        $invoiceIds      = self::sources($rows, DocumentType::INVOICE);
        $prescriptionIds = self::sources($rows, DocumentType::PRESCRIPTION);
        $invoices        = [] === $invoiceIds ? [] : $this->invoices->states($clinicId, $invoiceIds);
        $prescriptions   = [] === $prescriptionIds ? [] : $this->prescriptions->states($clinicId, $prescriptionIds);

        return array_values(array_filter($rows, static fn (ListedDocument $row): bool => match ($row->type) {
            DocumentType::INVOICE->value      => null !== ($invoices[$row->sourceRef] ?? null)?->paymentStatus,
            DocumentType::PRESCRIPTION->value => true === ($prescriptions[$row->sourceRef] ?? null)?->issued,
            default                           => true,
        }));
    }

    /**
     * @param list<ListedDocument> $rows
     *
     * @return list<string>
     */
    private static function sources(array $rows, DocumentType $type): array
    {
        $sources = [];

        foreach ($rows as $row) {
            if ($type->value === $row->type) {
                $sources[] = $row->sourceRef;
            }
        }

        return array_values(array_unique($sources));
    }
}
