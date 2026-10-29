<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Messaging;

use App\Context\Consultation\Application\Command\EmailConsultationDocuments\EmailConsultationDocuments;
use App\Context\Consultation\Application\Port\DocumentJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * EmailConsultationDocuments is routed to `async` (messenger.yaml) and
 * dispatched on the raw command bus — the application command bus expects
 * a handled result.
 */
final readonly class MessengerDocumentJobs implements DocumentJobsInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function emailDocuments(string $clinicId, string $consultationId, array $documents, string $requestId): void
    {
        $this->commandBus->dispatch(new EmailConsultationDocuments($clinicId, $consultationId, $documents, $requestId));
    }
}
