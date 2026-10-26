<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\EventSubscriber;

use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmission;
use App\Context\Billing\Domain\Event\ProfessionalDocumentIssuedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Out of the issuing transaction: request the transmission. A failure here
 * is retried by Messenger and caught up by the hourly reconciliation; it never
 * reaches the counter.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class OnProfessionalDocumentIssuedRequestTransmission
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(ProfessionalDocumentIssuedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new RequestDocumentTransmission($event->clinicId, $event->documentId, $event->kind));
    }
}
