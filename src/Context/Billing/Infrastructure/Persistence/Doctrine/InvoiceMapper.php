<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceLineEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\PaymentEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class InvoiceMapper
{
    /**
     * @param list<InvoiceLineEntity> $lineEntities
     * @param list<PaymentEntity>     $paymentEntities
     */
    public function toDomain(InvoiceEntity $entity, array $lineEntities, array $paymentEntities): Invoice
    {
        $currency = CurrencyCode::fromString($entity->getCurrency());

        $lines = array_map(
            fn (InvoiceLineEntity $line): InvoiceLine => $this->lineToDomain($line, $currency),
            $lineEntities,
        );

        $payments = array_map(
            fn (PaymentEntity $payment): Payment => $this->paymentToDomain($payment, $currency),
            $paymentEntities,
        );

        $discountKind = $entity->getDiscountKind();
        $discount     = null;

        if (null !== $discountKind && null !== $entity->getDiscountValue()) {
            $discount = Discount::reconstitute(
                $discountKind,
                $entity->getDiscountValue(),
                $entity->getDiscountReason(),
            );
        }

        $payerClientId = $entity->getPayerClientId();

        return Invoice::reconstitute(
            id: InvoiceId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            number: null !== $entity->getNumber() ? InvoiceNumber::fromString($entity->getNumber()) : null,
            status: $entity->getStatus(),
            admissionId: null !== $entity->getAdmissionId()
                ? AdmissionId::fromString($entity->getAdmissionId()->toRfc4122())
                : null,
            payer: Payer::create(
                null !== $payerClientId ? PayerId::fromString($payerClientId->toRfc4122()) : null,
                $entity->getPayerName(),
                $entity->getPayerAddressLines(),
            ),
            lines: $lines,
            payments: $payments,
            discount: $discount,
            currency: $currency,
            notes: $entity->getNotes(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            issuedAt: $entity->getIssuedAt(),
            paidAt: $entity->getPaidAt(),
            cancelledAt: $entity->getCancelledAt(),
            cancellationReason: $entity->getCancellationReason(),
            creditedMinorUnits: $entity->getCreditedMinorUnits(),
            refundedMinorUnits: $entity->getRefundedMinorUnits(),
            counterSalePayer: $entity->getCounterSalePayer(),
        );
    }

    public function toEntity(Invoice $invoice, ?InvoiceEntity $entity = null): InvoiceEntity
    {
        $entity ??= new InvoiceEntity();

        $entity->setId(Uuid::fromString($invoice->id()->toString()));
        $entity->setClinicId(Uuid::fromString($invoice->clinicId()->toString()));
        $entity->setCreatedAt($invoice->createdAt());
        $entity->setCounterSalePayer($invoice->counterSalePayer());

        $this->applyMutableState($invoice, $entity);

        return $entity;
    }

    public function applyMutableState(Invoice $invoice, InvoiceEntity $entity): void
    {
        $admissionId   = $invoice->admissionId();
        $payerClientId = $invoice->payer()->clientId();
        $discount      = $invoice->discount();

        $entity->setNumber($invoice->number()?->toString());
        $entity->setStatus($invoice->status());
        $entity->setAdmissionId(null !== $admissionId ? Uuid::fromString($admissionId->toString()) : null);
        $entity->setPayerClientId(null !== $payerClientId ? Uuid::fromString($payerClientId->toString()) : null);
        $entity->setPayerName($invoice->payer()->displayName());
        $entity->setPayerAddressLines($invoice->payer()->addressLines());
        $entity->setCurrency($invoice->currency()->toString());
        $entity->setDiscountKind($discount?->kind());
        $entity->setDiscountValue($discount?->value());
        $entity->setDiscountReason($discount?->reason());
        $entity->setNotes($invoice->notes());
        $entity->setUpdatedAt($invoice->updatedAt());
        $entity->setIssuedAt($invoice->issuedAt());
        $entity->setPaidAt($invoice->paidAt());
        $entity->setCancelledAt($invoice->cancelledAt());
        $entity->setCancellationReason($invoice->cancellationReason());
        $entity->setCreditedMinorUnits($invoice->amountCredited()->minorUnits());
        $entity->setRefundedMinorUnits($invoice->amountRefunded()->minorUnits());
    }

    public function lineToEntity(InvoiceLine $line, InvoiceId $invoiceId, int $position): InvoiceLineEntity
    {
        $entity = new InvoiceLineEntity();

        $entity->setId(Uuid::fromString($line->id()->toString()));
        $entity->setInvoiceId(Uuid::fromString($invoiceId->toString()));
        $entity->setLabel($line->label());
        $entity->setCode($line->code());
        $entity->setQuantity($line->quantity());
        $entity->setUnitPriceMinorUnits($line->unitNetPrice()->minorUnits());
        $entity->setNetMinorUnits($line->netAmount()->minorUnits());
        $entity->setTaxMinorUnits($line->taxAmount()->minorUnits());
        $entity->setGrossMinorUnits($line->grossAmount()->minorUnits());
        $entity->setTaxRatePercent($line->taxRatePercent());
        $entity->setTaxCategoryCode($line->taxCategoryCode());
        $entity->setSource($line->source());
        $entity->setSourceReference($line->sourceReference());
        $entity->setPosition($position);

        return $entity;
    }

    public function paymentToEntity(
        Payment $payment,
        InvoiceId $invoiceId,
        ClinicId $clinicId,
        string $currency,
        int $position,
    ): PaymentEntity {
        $entity   = new PaymentEntity();
        $recorder = $payment->recordedByUserId();

        $entity->setId(Uuid::fromString($payment->id()->toString()));
        $entity->setInvoiceId(Uuid::fromString($invoiceId->toString()));
        $entity->setClinicId(Uuid::fromString($clinicId->toString()));
        $entity->setMethod($payment->method());
        $entity->setAmountMinorUnits($payment->amount()->minorUnits());
        $entity->setCurrency($currency);
        $entity->setReceivedAt($payment->receivedAt());
        $entity->setReference($payment->reference());
        $entity->setRecordedByUserId(null !== $recorder ? Uuid::fromString($recorder) : null);
        $entity->setPosition($position);

        return $entity;
    }

    private function lineToDomain(InvoiceLineEntity $entity, CurrencyCode $currency): InvoiceLine
    {
        return InvoiceLine::create(
            id: InvoiceLineId::fromString($entity->getId()->toRfc4122()),
            label: $entity->getLabel(),
            code: $entity->getCode(),
            quantity: $this->trimDecimal($entity->getQuantity()),
            unitNetPrice: Money::fromMinorUnits($entity->getUnitPriceMinorUnits(), $currency),
            taxCategoryCode: $entity->getTaxCategoryCode(),
            taxRatePercent: $this->trimDecimal($entity->getTaxRatePercent()),
            netAmount: Money::fromMinorUnits($entity->getNetMinorUnits(), $currency),
            taxAmount: Money::fromMinorUnits($entity->getTaxMinorUnits(), $currency),
            grossAmount: Money::fromMinorUnits($entity->getGrossMinorUnits(), $currency),
            source: $entity->getSource(),
            sourceReference: $entity->getSourceReference(),
        );
    }

    private function paymentToDomain(PaymentEntity $entity, CurrencyCode $currency): Payment
    {
        return Payment::create(
            id: PaymentId::fromString($entity->getId()->toRfc4122()),
            method: $entity->getMethod(),
            amount: Money::fromMinorUnits($entity->getAmountMinorUnits(), $currency),
            receivedAt: $entity->getReceivedAt(),
            reference: $entity->getReference(),
            recordedByUserId: $entity->getRecordedByUserId()?->toRfc4122(),
        );
    }

    /**
     * DECIMAL columns come back zero-padded ("2.0000"); the value objects expect
     * the shape they were created with.
     */
    private function trimDecimal(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        $trimmed = mb_rtrim(mb_rtrim($value, '0'), '.');

        return '' === $trimmed ? '0' : $trimmed;
    }
}
