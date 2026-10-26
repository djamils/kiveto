<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Port;

/**
 * One status a lifecycle message (CDAR) reports for one document.
 */
final readonly class ParsedLifecycleMessage
{
    public function __construct(
        public int $statusCode,
        public ?string $reasonCode,
        public ?string $reasonText,
        public string $invoiceNumber,
        public ?\DateTimeImmutable $invoiceIssueDate,
        public ?string $typeCode,
        public ?string $sellerSiren,
        public \DateTimeImmutable $issuedAt,
    ) {
    }
}
