<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\AddManualInvoiceLine;

use App\Context\Billing\Application\Service\InvoiceLineFactory;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddManualInvoiceLineHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceLineFactory $lines,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddManualInvoiceLine $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );

        $invoice->addLine(
            $this->lines->line(
                clinicId: $command->clinicId,
                label: $command->label,
                code: null,
                quantity: $command->quantity,
                enteredUnitPrice: Money::fromMinorUnits($command->unitPriceMinorUnits, $invoice->currency()),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: $command->taxCategoryCode,
                source: InvoiceLineSource::MANUAL,
                categoryRequired: true,
            ),
            $this->clock->now(),
        );

        $this->invoices->save($invoice);
    }
}
