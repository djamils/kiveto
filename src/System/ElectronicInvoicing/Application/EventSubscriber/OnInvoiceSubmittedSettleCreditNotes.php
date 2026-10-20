<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\EventSubscriber;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Application\Command\SettleCreditNote\SettleCreditNote;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionSubmitted;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An invoice reached the platform: its waiting credit notes go.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnInvoiceSubmittedSettleCreditNotes
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(TransmissionSubmitted $event): void
    {
        $invoice = $this->transmissions->get(OutboundTransmissionId::fromString($event->transmissionId));

        if (TransmissionKind::INVOICE !== $invoice->kind()) {
            return;
        }

        foreach ($this->transmissions->awaitingCreditNotesOf($invoice->sellerSiren(), $invoice->documentNumber()) as $creditNote) {
            $this->commandBus->dispatch(new SettleCreditNote($creditNote->id()->toString()));
        }
    }
}
