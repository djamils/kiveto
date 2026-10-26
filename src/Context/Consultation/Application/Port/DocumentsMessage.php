<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The CONSULTATION_DOCUMENTS e-mail, with exactly the parameters of
 * Notification's template.
 */
final readonly class DocumentsMessage
{
    /**
     * @param array<string, string|list<string>> $parameters
     * @param list<StoredDocument>               $sendOnlyFiles  rendered for this e-mail only (Notification deletes them with its content)
     * @param list<StoredDocument>               $requesterFiles kept by their owner (the invoice PDF, Billing's)
     */
    public function __construct(
        public string $recipientAddress,
        public string $recipientName,
        public string $clinicId,
        public string $clientId,
        public string $consultationId,
        public string $idempotencyKey,
        public array $parameters,
        public array $sendOnlyFiles,
        public array $requesterFiles,
    ) {
    }
}
