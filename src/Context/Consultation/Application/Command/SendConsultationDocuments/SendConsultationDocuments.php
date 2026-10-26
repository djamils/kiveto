<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\SendConsultationDocuments;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Envoyer par e-mail » from the documents panel of a closed consultation:
 * the ticked documents are checked and their e-mail queued at once; a worker
 * renders the PDFs (EmailConsultationDocuments). Returns a
 * ConsultationDocumentsQueued for the toast.
 *
 * @throws \App\Context\Consultation\Application\Service\Documents\ConsultationDocumentsNotSendable
 */
final readonly class SendConsultationDocuments implements CommandInterface
{
    /**
     * @param list<string> $documents `report`, `prescription:<id>`, `invoice`
     */
    public function __construct(
        public string $clinicId,
        public string $consultationId,
        public array $documents,
        public ?string $actorUserId = null,
    ) {
    }
}
