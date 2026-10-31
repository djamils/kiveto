<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ShareConsultationReport;

final readonly class ReportShareResult
{
    /**
     * @param bool $linked whether the owner already reaches it (else: once they activate their Portal)
     */
    public function __construct(
        public string $documentId,
        public bool $linked,
    ) {
    }
}
