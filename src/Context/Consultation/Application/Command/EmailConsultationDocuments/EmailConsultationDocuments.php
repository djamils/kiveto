<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\EmailConsultationDocuments;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The documents e-mail of one « Envoyer par e-mail » click, queued on
 * `async` by SendConsultationDocuments.
 */
final readonly class EmailConsultationDocuments implements CommandInterface
{
    /**
     * @param list<string> $documents `report`, `prescription:<id>`, `invoice`
     */
    public function __construct(
        public string $clinicId,
        public string $consultationId,
        public array $documents,
        public string $requestId,
    ) {
    }
}
