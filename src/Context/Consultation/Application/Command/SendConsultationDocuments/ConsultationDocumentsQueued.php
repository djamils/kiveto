<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\SendConsultationDocuments;

/**
 * The documents e-mail queued: to whom, with how many PDFs.
 */
final readonly class ConsultationDocumentsQueued
{
    public function __construct(
        public string $requestId,
        public string $recipientName,
        public string $recipientEmail,
        public int $documentCount,
    ) {
    }
}
