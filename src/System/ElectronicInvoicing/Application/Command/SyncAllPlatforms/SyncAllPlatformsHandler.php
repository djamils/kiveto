<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions\ReleasePendingTransmissions;
use App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions\ReleasePendingTransmissionsHandler;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Application\Command\SyncPlatformFlows\SyncPlatformFlows;
use App\System\ElectronicInvoicing\Application\Port\SubmissionScheduleInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Every tick: one synchronisation per polled organisation (batch transport),
 * a resend of every transmission whose submission is overdue — at most once
 * per window, the marker re-stamped — and the release of transmissions still
 * waiting although their organisation is now connected.
 */
#[AsMessageHandler]
final readonly class SyncAllPlatformsHandler
{
    public function __construct(
        private PlatformConnectionRepositoryInterface $connections,
        private SubmissionScheduleInterface $schedule,
        private OutboundTransmissionRepositoryInterface $transmissions,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private CommandBusInterface $commandBus,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SyncAllPlatforms $command): void
    {
        foreach ($this->connections->polled() as $connection) {
            $this->externalApiCommandBus->send(
                new SyncPlatformFlows($connection->organisationId()->value()),
                new TransportNamesStamp(['async_external_batch']),
            );
        }

        $now    = $this->clock->now();
        $before = $now->modify(ReleasePendingTransmissionsHandler::RESEND_WINDOW);

        foreach ($this->schedule->markSubmissionRequested($this->schedule->staleRequested($before), $now, $before) as $id) {
            $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($id));
            $connectionId = $transmission->connectionId();

            if (null === $connectionId) {
                continue;
            }

            $send = $transmission->acknowledgeSubmissionRequested($connectionId, $now);

            $this->transmissions->save($transmission);
            $this->domainEventPublisher->publish($transmission);

            if ($send) {
                $this->externalApiCommandBus->send(new SubmitTransmission($id));
            }
        }

        foreach ($this->schedule->organisationsWaitingForConnection() as $organisation) {
            if (true === $this->connections->byOrganisation(Siren::fromInput($organisation))?->acceptsSubmissions()) {
                $this->commandBus->dispatch(new ReleasePendingTransmissions($organisation));
            }
        }
    }
}
