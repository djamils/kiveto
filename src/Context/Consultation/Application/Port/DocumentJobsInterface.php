<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * Queues the documents e-mail for a worker, in the current transaction.
 */
interface DocumentJobsInterface
{
    /**
     * @param list<string> $documents `report`, `prescription:<id>`, `invoice`
     */
    public function emailDocuments(string $clinicId, string $consultationId, array $documents, string $requestId): void;
}
