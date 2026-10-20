<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\ProfessionalDocumentIssuedIntegrationEvent;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A professional invoice is issued: in the same transaction its neutral
 * document is frozen — the seller's identity and the amount paid at the desk
 * as they are now — its transmission is marked requested and the request is
 * written to the outbox. Private clients' invoices go nowhere (e-reporting
 * comes later).
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class InvoiceIssuedIntegrationBridge
{
    public function __construct(
        private IntegrationEventPublisher $integrationEventPublisher,
        private TransmissionStatusProjectionInterface $projection,
        private InvoiceRepositoryInterface $invoices,
        private SellerIdentityProviderInterface $sellers,
        private ElectronicInvoiceTransmitterInterface $transmitter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(InvoiceIssued $event): void
    {
        if (ProcessingRule::B2B->value !== $event->processingRule) {
            return;
        }

        $invoice  = $this->invoices->get(ClinicId::fromString($event->clinicId), InvoiceId::fromString($event->invoiceId));
        $document = $this->transmitter->freezeInvoice($invoice, $this->sellers->forClinic($event->clinicId));

        $this->projection->markRequested($event->invoiceId, TransmissionStatusProjectionInterface::KIND_INVOICE, $document, $this->clock->now());

        $this->integrationEventPublisher->publish(new ProfessionalDocumentIssuedIntegrationEvent(
            clinicId: $event->clinicId,
            documentId: $event->invoiceId,
            kind: TransmissionStatusProjectionInterface::KIND_INVOICE,
            number: $event->number,
            issuedAt: $event->issuedAt,
        ));
    }
}
