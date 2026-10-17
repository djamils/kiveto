<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\CreditNoteEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A credit note never changes once issued, so its lines are kept as one JSON
 * snapshot on its row rather than in a table of their own.
 */
final readonly class CreditNoteMapper
{
    public function toDomain(CreditNoteEntity $entity): CreditNote
    {
        $currency = CurrencyCode::fromString($entity->getCurrency());
        $payer    = $entity->getPayerClientId();
        $issuer   = $entity->getIssuedByUserId();

        return CreditNote::reconstitute(
            id: CreditNoteId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            number: InvoiceNumber::fromString($entity->getNumber()),
            invoiceId: InvoiceId::fromString($entity->getInvoiceId()->toRfc4122()),
            invoiceNumber: $entity->getInvoiceNumber(),
            payer: Payer::create(
                null !== $payer ? PayerId::fromString($payer->toRfc4122()) : null,
                $entity->getPayerName(),
                $entity->getPayerAddressLines(),
                $entity->getPayerAttention(),
            ),
            lines: array_map(fn (array $line): InvoiceLine => $this->lineToDomain($line, $currency), $entity->getLines()),
            reason: $entity->getReason(),
            refundMethod: $entity->getRefundMethod(),
            refunded: Money::fromMinorUnits($entity->getRefundedMinorUnits(), $currency),
            currency: $currency,
            issuedAt: $entity->getIssuedAt(),
            issuedByUserId: $issuer?->toRfc4122(),
        );
    }

    public function toEntity(CreditNote $creditNote): CreditNoteEntity
    {
        $entity = new CreditNoteEntity();
        $payer  = $creditNote->payer()->clientId();
        $issuer = $creditNote->issuedByUserId();

        $entity->setId(Uuid::fromString($creditNote->id()->toString()));
        $entity->setClinicId(Uuid::fromString($creditNote->clinicId()->toString()));
        $entity->setNumber($creditNote->number()->toString());
        $entity->setInvoiceId(Uuid::fromString($creditNote->invoiceId()->toString()));
        $entity->setInvoiceNumber($creditNote->invoiceNumber());
        $entity->setPayerClientId(null !== $payer ? Uuid::fromString($payer->toString()) : null);
        $entity->setPayerName($creditNote->payer()->displayName());
        $entity->setPayerAddressLines($creditNote->payer()->addressLines());
        $entity->setPayerAttention($creditNote->payer()->attention());
        $entity->setLines(array_map(fn (InvoiceLine $line): array => $this->lineToArray($line), $creditNote->lines()));
        $entity->setReason($creditNote->reason());
        $entity->setRefundMethod($creditNote->refundMethod());
        $entity->setRefundedMinorUnits($creditNote->refunded()->minorUnits());
        $entity->setTotalNetMinorUnits($creditNote->totalNet()->minorUnits());
        $entity->setTotalTaxMinorUnits($creditNote->totalTax()->minorUnits());
        $entity->setTotalGrossMinorUnits($creditNote->totalGross()->minorUnits());
        $entity->setCurrency($creditNote->currency()->toString());
        $entity->setIssuedAt($creditNote->issuedAt());
        $entity->setIssuedByUserId(null !== $issuer ? Uuid::fromString($issuer) : null);

        return $entity;
    }

    /**
     * @return array<string, int|string|null>
     */
    private function lineToArray(InvoiceLine $line): array
    {
        return [
            'id'              => $line->id()->toString(),
            'label'           => $line->label(),
            'code'            => $line->code(),
            'quantity'        => $line->quantity(),
            'unitNet'         => $line->unitNetPrice()->minorUnits(),
            'taxCategoryCode' => $line->taxCategoryCode(),
            'taxRatePercent'  => $line->taxRatePercent(),
            'net'             => $line->netAmount()->minorUnits(),
            'tax'             => $line->taxAmount()->minorUnits(),
            'gross'           => $line->grossAmount()->minorUnits(),
            'sourceReference' => $line->sourceReference(),
        ];
    }

    /**
     * @param array<string, int|string|null> $line
     */
    private function lineToDomain(array $line, CurrencyCode $currency): InvoiceLine
    {
        $money = static fn (string $key): Money => Money::fromMinorUnits((int) ($line[$key] ?? 0), $currency);
        $text  = static fn (string $key): ?string => isset($line[$key]) ? (string) $line[$key] : null;

        return InvoiceLine::create(
            id: InvoiceLineId::fromString((string) $text('id')),
            label: (string) $text('label'),
            code: $text('code'),
            quantity: (string) $text('quantity'),
            unitNetPrice: $money('unitNet'),
            taxCategoryCode: $text('taxCategoryCode'),
            taxRatePercent: (string) $text('taxRatePercent'),
            netAmount: $money('net'),
            taxAmount: $money('tax'),
            grossAmount: $money('gross'),
            source: InvoiceLineSource::CREDIT,
            sourceReference: $text('sourceReference'),
        );
    }
}
