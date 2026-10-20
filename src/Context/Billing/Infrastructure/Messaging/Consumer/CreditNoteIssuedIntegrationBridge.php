<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Event\ProfessionalDocumentIssuedIntegrationEvent;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A credit note of a professional invoice follows its invoice to the
 * platform, frozen like it when issued. One of an invoice issued without a fiscal qualification (before
 * the reform was implemented) counts as a private one and is skipped.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class CreditNoteIssuedIntegrationBridge
{
    public function __construct(
        private IntegrationEventPublisher $integrationEventPublisher,
        private TransmissionStatusProjectionInterface $projection,
        private CreditNoteRepositoryInterface $creditNotes,
        private SellerIdentityProviderInterface $sellers,
        private ElectronicInvoiceTransmitterInterface $transmitter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreditNoteIssued $event): void
    {
        if (ProcessingRule::B2B->value !== $event->processingRule) {
            return;
        }

        $creditNote = $this->creditNotes->get(ClinicId::fromString($event->clinicId), CreditNoteId::fromString($event->creditNoteId));
        $document   = $this->transmitter->freezeCreditNote($creditNote, $this->sellers->forClinic($event->clinicId));

        $this->projection->markRequested($event->creditNoteId, TransmissionStatusProjectionInterface::KIND_CREDIT_NOTE, $document, $this->clock->now());

        $this->integrationEventPublisher->publish(new ProfessionalDocumentIssuedIntegrationEvent(
            clinicId: $event->clinicId,
            documentId: $event->creditNoteId,
            kind: TransmissionStatusProjectionInterface::KIND_CREDIT_NOTE,
            number: $event->number,
            issuedAt: $event->issuedAt,
        ));
    }
}
