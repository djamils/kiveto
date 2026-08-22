<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordInvoicePayment;

use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordInvoicePaymentHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordInvoicePayment $command): void
    {
        $invoice = $this->invoices->get(
            ClinicId::fromString($command->clinicId),
            InvoiceId::fromString($command->invoiceId),
        );

        $now = $this->clock->now();

        $invoice->recordPayment(
            Payment::create(
                id: PaymentId::fromString($this->uuidGenerator->generate()),
                method: PaymentMethod::from($command->method),
                amount: Money::fromMinorUnits($command->amountMinorUnits, $invoice->currency()),
                receivedAt: $now,
                reference: $command->reference,
                recordedByUserId: $command->recordedByUserId,
            ),
            $now,
        );

        $this->invoices->save($invoice);
    }
}
