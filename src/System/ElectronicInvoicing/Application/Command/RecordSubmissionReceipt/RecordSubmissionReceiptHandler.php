<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordSubmissionReceipt;

use App\Shared\Application\Event\DomainEventPublisher;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordSubmissionReceiptHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RecordSubmissionReceipt $command): void
    {
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));

        $transmission->markSubmitted(FlowId::fromString($command->flowId), new \DateTimeImmutable($command->submittedAt));

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);
    }
}
