<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus;

use App\Shared\Application\Event\DomainEventPublisher;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordLifecycleStatusHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RecordLifecycleStatus $command): void
    {
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));

        $transmission->recordLifecycleStatus(
            LifecycleStatusCode::from($command->statusCode),
            $command->reasonCode,
            $command->reasonText,
            FlowId::fromString($command->sourceFlowId),
            new \DateTimeImmutable($command->at),
        );

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);
    }
}
