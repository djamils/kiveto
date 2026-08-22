<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RemoveInvoiceLine;

use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveInvoiceLineHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemoveInvoiceLine $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );

        $invoice->removeLine(InvoiceLineId::fromString($command->lineId), $this->clock->now());

        $this->invoices->save($invoice);
    }
}
