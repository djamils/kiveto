<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatus;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\TransmissionStatusView;

final readonly class CommandBusElectronicInvoiceTransmitter implements ElectronicInvoiceTransmitterInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private TransmittableInvoiceMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function freezeInvoice(Invoice $invoice, SellerIdentity $seller): array
    {
        return $this->mapper->fromInvoice($invoice, $seller)->toArray();
    }

    public function freezeCreditNote(CreditNote $creditNote, SellerIdentity $seller): array
    {
        return $this->mapper->fromCreditNote($creditNote, $seller)->toArray();
    }

    public function transmit(string $clinicId, array $document): void
    {
        $this->commandBus->dispatch(new RequestInvoiceTransmission($clinicId, TransmittableInvoice::fromArray($document)));
    }

    public function status(string $documentId): ?TransmissionStatusUpdate
    {
        $view = $this->queryBus->ask(new GetTransmissionStatus($documentId));

        if (!$view instanceof TransmissionStatusView) {
            return null;
        }

        return new TransmissionStatusUpdate(
            documentId: $view->documentId,
            sequence: $view->sequence,
            state: $view->state,
            blockReason: $view->blockReason,
            statusCode: $view->statusCode,
            statusLabel: $view->statusLabel,
            reasonCode: $view->reasonCode,
            reasonText: $view->reasonText,
            requiresAction: $view->requiresAction,
            updatedAt: $this->clock->now(),
        );
    }
}
