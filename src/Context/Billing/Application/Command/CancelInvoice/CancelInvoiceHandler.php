<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CancelInvoice;

use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CancelInvoice $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );

        $invoice->cancel($command->reason, $this->clock->now());

        $this->invoices->save($invoice);
        $this->domainEventPublisher->publish($invoice);
    }
}
