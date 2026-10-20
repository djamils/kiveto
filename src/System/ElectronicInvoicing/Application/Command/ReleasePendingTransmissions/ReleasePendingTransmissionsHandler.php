<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Application\Port\SubmissionScheduleInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Releases what waited for the organisation's connection: every transmission
 * still without one is marked row by row — two concurrent releases never
 * mark it twice — queued on the connection and sent, invoices before credit
 * notes.
 */
#[AsMessageHandler]
final readonly class ReleasePendingTransmissionsHandler
{
    /** A transmission is resent at most once per window. */
    public const string RESEND_WINDOW = '-15 minutes';

    public function __construct(
        private SubmissionScheduleInterface $schedule,
        private PlatformConnectionRepositoryInterface $connections,
        private OutboundTransmissionRepositoryInterface $transmissions,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReleasePendingTransmissions $command): int
    {
        $connection = $this->connections->byOrganisation(Siren::fromInput($command->organisationId));

        if (null === $connection || !$connection->acceptsSubmissions()) {
            return 0;
        }

        $now    = $this->clock->now();
        $marked = $this->schedule->markSubmissionRequested(
            $this->schedule->preparedWithoutConnection($command->organisationId),
            $now,
            $now->modify(self::RESEND_WINDOW),
        );
        $sent = 0;

        foreach ($marked as $id) {
            $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($id));
            $send         = $transmission->acknowledgeSubmissionRequested($connection->id(), $now);

            $this->transmissions->save($transmission);
            $this->domainEventPublisher->publish($transmission);

            if ($send) {
                $this->externalApiCommandBus->send(new SubmitTransmission($id));
                ++$sent;
            }
        }

        return $sent;
    }
}
