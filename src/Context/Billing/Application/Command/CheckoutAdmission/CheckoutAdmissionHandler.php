<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CheckoutAdmission;

use App\Context\Billing\Application\Port\AdmissionCoordinatorInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The desk's one-shot operation: number the invoice, take the money, send the
 * client home. A partial payment leaves the visit open — the client is still
 * standing at the counter with a balance to settle.
 */
#[AsMessageHandler]
final readonly class CheckoutAdmissionHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNumberGeneratorInterface $numbers,
        private AdmissionCoordinatorInterface $admissions,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CheckoutAdmission $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $invoice  = $this->invoices->get($clinicId, InvoiceId::fromString($command->invoiceId));
        $now      = $this->clock->now();

        if ($invoice->status()->isEditable()) {
            $invoice->issue($this->numbers->next($clinicId, (int) $now->format('Y')), $now);
        }

        if ($command->amountMinorUnits > 0) {
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
        }

        $this->invoices->save($invoice);

        $admissionId = $invoice->admissionId();

        if (null !== $admissionId && InvoiceStatus::PAID === $invoice->status()) {
            $this->admissions->closeAfterCheckout($admissionId->toString(), $command->clinicId);
        }
    }
}
