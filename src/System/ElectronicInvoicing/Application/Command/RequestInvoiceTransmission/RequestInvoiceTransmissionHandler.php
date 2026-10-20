<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Records the document to transmit — no rendering, no network. When the
 * organisation already has a verified connection the submission is queued
 * right away (async_external, in the same transaction); otherwise the
 * transmission waits until a connection becomes active.
 */
#[AsMessageHandler]
final readonly class RequestInvoiceTransmissionHandler
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private PlatformConnectionRepositoryInterface $connections,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestInvoiceTransmission $command): string
    {
        $invoice  = $command->invoice;
        $tracking = TrackingId::fromString($invoice->documentId);
        $existing = $this->transmissions->byTrackingId($tracking);

        if (null !== $existing) {
            return $existing->id()->toString();
        }

        $now          = $this->clock->now();
        $seller       = Siren::fromInput($invoice->seller->siren);
        $kind         = TransmissionKind::fromTypeCode($invoice->typeCode);
        $transmission = OutboundTransmission::prepare(
            id: OutboundTransmissionId::fromString($this->uuids->generate()),
            clinicId: $command->clinicId,
            kind: $kind,
            trackingId: $tracking,
            documentNumber: $invoice->number,
            documentDate: $invoice->issueDate,
            sellerSiren: $seller,
            buyerSiren: '' !== $invoice->buyer->siren ? Siren::fromInput($invoice->buyer->siren) : null,
            precedingDocumentNumber: $invoice->precedingInvoiceNumber,
            payload: $invoice->toArray(),
            now: $now,
        );

        if (TransmissionKind::CREDIT_NOTE === $kind && null !== $invoice->precedingInvoiceNumber) {
            $preceding = $this->transmissions->byDocument($seller, TransmissionKind::INVOICE, $invoice->precedingInvoiceNumber);

            if (null !== $preceding && !self::canBeCredited($preceding)) {
                $transmission->markNotRequired('The credited invoice is void for the tax authority or was never accepted.', $now);
            }
        }

        $connection = $this->connections->byOrganisation($seller);
        $send       = TransmissionState::PREPARED === $transmission->state()
            && null !== $connection
            && $connection->acceptsSubmissions()
            && $transmission->requestSubmission($connection->id(), $now);

        $this->transmissions->save($transmission);
        $this->domainEventPublisher->publish($transmission);

        if ($send) {
            $this->externalApiCommandBus->send(new SubmitTransmission($transmission->id()->toString()));
        }

        return $transmission->id()->toString();
    }

    /**
     * A credit note follows its invoice only while the invoice still exists
     * for the tax authority and the platform took it.
     */
    public static function canBeCredited(OutboundTransmission $invoice): bool
    {
        if (!$invoice->isFiscallyAlive()) {
            return false;
        }

        return !\in_array($invoice->state(), [TransmissionState::BLOCKED, TransmissionState::INADMISSIBLE, TransmissionState::NOT_REQUIRED], true);
    }
}
