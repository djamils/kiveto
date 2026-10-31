<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

final readonly class PortalDocument
{
    /**
     * @param list<string>       $petIds
     * @param \DateTimeImmutable $issuedAt in the clinic's time zone
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public array $petIds,
        public \DateTimeImmutable $issuedAt,
        public bool $unread,
        public ?PortalInvoiceSummary $invoice,
        public ?PortalPrescriptionSummary $prescription,
    ) {
    }
}
