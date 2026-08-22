<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueInvoice;

use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNumberGeneratorInterface $numbers,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IssueInvoice $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $invoice  = $this->invoices->get($clinicId, InvoiceId::fromString($command->invoiceId));

        $now = $this->clock->now();

        $invoice->issue($this->numbers->next($clinicId, (int) $now->format('Y')), $now);

        $this->invoices->save($invoice);
    }
}
