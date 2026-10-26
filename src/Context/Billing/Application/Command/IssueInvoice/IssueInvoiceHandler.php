<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueInvoice;

use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Domain\Policy\ElectronicInvoiceReadiness;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\FiscalCalendar;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNumberGeneratorInterface $numbers,
        private ClockInterface $clock,
        private SellerIdentityProviderInterface $sellers,
        private DomainEventPublisher $domainEventPublisher,
        private ElectronicInvoiceReadiness $readiness = new ElectronicInvoiceReadiness(),
    ) {
    }

    public function __invoke(IssueInvoice $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $invoice  = $this->invoices->get($clinicId, InvoiceId::fromString($command->invoiceId));

        $now = $this->clock->now();

        $seller = $this->sellers->forClinic($command->clinicId);

        $this->readiness->assertReady($invoice, $seller);
        $invoice->issue($this->numbers->next($clinicId, FiscalCalendar::year($now)), $seller, $now);

        $this->invoices->save($invoice);
        $this->domainEventPublisher->publish($invoice);
    }
}
