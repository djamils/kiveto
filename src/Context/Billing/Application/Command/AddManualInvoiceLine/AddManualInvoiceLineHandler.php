<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\AddManualInvoiceLine;

use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddManualInvoiceLineHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private TaxResolverInterface $taxResolver,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddManualInvoiceLine $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );
        $currency = $invoice->currency();

        $net = (int) round((float) $command->quantity * $command->unitPriceMinorUnits);
        $tax = $this->taxResolver->resolve(
            $net,
            $currency->toString(),
            $command->taxCategoryCode,
            $command->clinicId,
        );

        $invoice->addLine(
            InvoiceLine::create(
                id: InvoiceLineId::fromString($this->uuidGenerator->generate()),
                label: $command->label,
                code: null,
                quantity: $command->quantity,
                unitNetPrice: Money::fromMinorUnits($command->unitPriceMinorUnits, $currency),
                taxCategoryCode: $command->taxCategoryCode,
                taxRatePercent: $tax->ratePercent,
                netAmount: Money::fromMinorUnits($tax->netMinorUnits, $currency),
                taxAmount: Money::fromMinorUnits($tax->taxMinorUnits, $currency),
                grossAmount: Money::fromMinorUnits($tax->grossMinorUnits, $currency),
                source: InvoiceLineSource::MANUAL,
            ),
            $this->clock->now(),
        );

        $this->invoices->save($invoice);
    }
}
