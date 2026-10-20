<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordSubmissionPrepared;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordSubmissionPreparedHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordSubmissionPrepared $command): void
    {
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));

        $transmission->markSubmitting(
            DocumentReference::of($command->documentId, $command->sha256),
            ElectronicAddress::of($command->electronicAddressScheme, $command->electronicAddressIdentifier),
            $this->clock->now(),
        );

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);
    }
}
