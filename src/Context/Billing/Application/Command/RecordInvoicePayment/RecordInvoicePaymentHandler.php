<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordInvoicePayment;

use App\Context\Billing\Application\Port\AdmissionCoordinatorInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A payment taken on an issued invoice — the rest of a bill, or one of the
 * means a bill is split over. The payment that settles a visit's invoice lets
 * the visit go, as the checkout does.
 */
#[AsMessageHandler]
final readonly class RecordInvoicePaymentHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private AdmissionCoordinatorInterface $admissions,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
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
        $this->domainEventPublisher->publish($invoice);

        $admissionId = $invoice->admissionId();

        if (null !== $admissionId && InvoiceStatus::PAID === $invoice->status()) {
            $this->admissions->closeAfterCheckout($admissionId->toString(), $command->clinicId);
        }
    }
}
