<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SettleCreditNote;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Application\Policy\CreditNoteFollowsItsInvoice;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A credit note released by its invoice is queued at once on the
 * organisation's verified connection; one that may not go waits or stays
 * internal, without counting a submission attempt. A credit note already
 * queued is left to its submission — settling it twice never sends it twice.
 */
#[AsMessageHandler]
final readonly class SettleCreditNoteHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private PlatformConnectionRepositoryInterface $connections,
        private CreditNoteFollowsItsInvoice $creditNotes,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SettleCreditNote $command): void
    {
        $now          = $this->clock->now();
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($command->transmissionId));
        $connection   = $this->connections->byOrganisation($transmission->sellerSiren());
        $waiting      = TransmissionState::AWAITING_INVOICE === $transmission->state();
        $send         = $this->creditNotes->settle($transmission, $now)
            && $waiting
            && null !== $connection
            && $connection->acceptsSubmissions()
            && $transmission->requestSubmission($connection->id(), $now);

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);

        if ($send) {
            $this->externalApiCommandBus->send(new SubmitTransmission($command->transmissionId));
        }
    }
}
