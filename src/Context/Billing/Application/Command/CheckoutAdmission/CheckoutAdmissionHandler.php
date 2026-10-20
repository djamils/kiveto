<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CheckoutAdmission;

use App\Context\Billing\Application\Port\AdmissionCoordinatorInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Domain\Policy\ElectronicInvoiceReadiness;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
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
        private SellerIdentityProviderInterface $sellers,
        private DomainEventPublisher $domainEventPublisher,
        private ElectronicInvoiceReadiness $readiness = new ElectronicInvoiceReadiness(),
    ) {
    }

    public function __invoke(CheckoutAdmission $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $invoice  = $this->invoices->get($clinicId, InvoiceId::fromString($command->invoiceId));
        $now      = $this->clock->now();

        $payment = $command->amountMinorUnits > 0
            ? Payment::create(
                id: PaymentId::fromString($this->uuidGenerator->generate()),
                method: PaymentMethod::from($command->method),
                amount: Money::fromMinorUnits($command->amountMinorUnits, $invoice->currency()),
                receivedAt: $now,
                reference: $command->reference,
                recordedByUserId: $command->recordedByUserId,
            )
            : null;

        if ($invoice->status()->isEditable()) {
            $seller = $this->sellers->forClinic($command->clinicId);

            // Checked before the number is drawn: a refusal leaves the
            // sequence untouched and the draft open.
            $this->readiness->assertReady($invoice, $seller);
            // The payment is part of the issue: it decides the framework.
            $invoice->issue($this->numbers->next($clinicId, (int) $now->format('Y')), $seller, $now, $payment);
        } elseif (null !== $payment) {
            $invoice->recordPayment($payment, $now);
        }

        $this->invoices->save($invoice);
        $this->domainEventPublisher->publish($invoice);

        $admissionId = $invoice->admissionId();

        if (null !== $admissionId && InvoiceStatus::PAID === $invoice->status()) {
            $this->admissions->closeAfterCheckout($admissionId->toString(), $command->clinicId);
        }
    }
}
