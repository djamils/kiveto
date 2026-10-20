<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing;

use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;

final readonly class CommandBusElectronicInvoiceTransmitter implements ElectronicInvoiceTransmitterInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TransmittableInvoiceMapper $mapper,
    ) {
    }

    public function transmitInvoice(Invoice $invoice, SellerIdentity $seller): void
    {
        $this->commandBus->dispatch(new RequestInvoiceTransmission($invoice->clinicId()->toString(), $this->mapper->fromInvoice($invoice, $seller)));
    }

    public function transmitCreditNote(CreditNote $creditNote, SellerIdentity $seller): void
    {
        $this->commandBus->dispatch(new RequestInvoiceTransmission($creditNote->clinicId()->toString(), $this->mapper->fromCreditNote($creditNote, $seller)));
    }
}
