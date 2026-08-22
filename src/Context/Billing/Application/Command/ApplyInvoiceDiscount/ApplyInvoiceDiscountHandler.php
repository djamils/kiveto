<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\ApplyInvoiceDiscount;

use App\Context\Billing\Domain\Exception\InvalidDiscountException;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplyInvoiceDiscountHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ApplyInvoiceDiscount $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );

        $invoice->applyDiscount($this->discountOf($command, $invoice->currency()->toString()), $this->clock->now());

        $this->invoices->save($invoice);
    }

    private function discountOf(ApplyInvoiceDiscount $command, string $currency): ?Discount
    {
        if (null === $command->kind) {
            return null;
        }

        if (null === $command->value) {
            throw new InvalidDiscountException('A discount needs a value.');
        }

        $kind = DiscountKind::from($command->kind);

        if (DiscountKind::PERCENTAGE === $kind) {
            return Discount::percentage($command->value, $command->reason);
        }

        if (1 !== preg_match('/^\d+$/', $command->value)) {
            throw new InvalidDiscountException(
                \sprintf('A fixed discount is expressed in minor units, got "%s".', $command->value),
            );
        }

        return Discount::fixed(
            Money::fromMinorUnits((int) $command->value, CurrencyCode::fromString($currency)),
            $command->reason,
        );
    }
}
