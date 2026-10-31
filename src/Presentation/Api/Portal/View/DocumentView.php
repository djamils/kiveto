<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListPublishedDocuments\PortalDocument;
use App\Presentation\Api\Portal\Support\ApiDate;

final readonly class DocumentView
{
    /**
     * @param list<string> $petIds
     */
    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['report', 'prescription', 'invoice', 'lab_result', 'certificate']])]
        public string $type,
        public string $title,
        public array $petIds,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $issuedAt,
        public bool $unread,
        public ?InvoiceSummaryView $invoice,
        public ?PrescriptionSummaryView $prescription,
    ) {
    }

    public static function of(PortalDocument $document): self
    {
        return new self(
            $document->id,
            $document->type,
            $document->title,
            $document->petIds,
            ApiDate::formatLocal($document->issuedAt),
            $document->unread,
            null === $document->invoice ? null : new InvoiceSummaryView(
                new MoneyView($document->invoice->totalMinorUnits, $document->invoice->currency),
                $document->invoice->paymentStatus,
            ),
            null === $document->prescription ? null : new PrescriptionSummaryView(
                $document->prescription->prescriptionId,
                ApiDate::nullableDate($document->prescription->validUntil),
                $document->prescription->renewable,
                null,
            ),
        );
    }
}
