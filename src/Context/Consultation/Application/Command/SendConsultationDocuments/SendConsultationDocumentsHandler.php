<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\SendConsultationDocuments;

use App\Context\Consultation\Application\Port\DocumentJobsInterface;
use App\Context\Consultation\Application\Service\Documents\ConsultationDocuments;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Validates and queues: nothing is rendered while the user waits. The
 * request id makes each click one e-mail (`consultation:<id>:documents:<requestId>`).
 */
#[AsMessageHandler]
final readonly class SendConsultationDocumentsHandler
{
    public function __construct(
        private ConsultationDocuments $documents,
        private DocumentJobsInterface $jobs,
        private UuidGeneratorInterface $uuids,
    ) {
    }

    public function __invoke(SendConsultationDocuments $command): ConsultationDocumentsQueued
    {
        $selection = $this->documents->select($command->clinicId, $command->consultationId, $command->documents);
        $requestId = $this->uuids->generate();

        $this->jobs->emailDocuments($command->clinicId, $selection->consultation->consultationId, array_values(array_unique($command->documents)), $requestId);

        \assert(null !== $selection->owner->email);

        return new ConsultationDocumentsQueued($requestId, $selection->owner->name, $selection->owner->email, $selection->count());
    }
}
