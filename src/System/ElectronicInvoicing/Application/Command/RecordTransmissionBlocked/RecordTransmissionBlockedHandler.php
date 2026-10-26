<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordTransmissionBlocked;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordTransmissionBlockedHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordTransmissionBlocked $command): void
    {
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));

        $transmission->markBlocked(BlockReason::from($command->reason), $command->detail, $this->clock->now());

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);
    }
}
