<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CounterSalePayer;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceLineEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\InvoiceMapper;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceMapperTest extends TestCase
{
    private const string INVOICE_ID   = '01960000-0000-7000-8000-000000000901';
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000902';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-000000000903';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-000000000904';
    private const string LINE_ID      = '01960000-0000-7000-8000-000000000905';
    private const string PAYMENT_ID   = '01960000-0000-7000-8000-000000000906';
    private const string USER_ID      = '01960000-0000-7000-8000-000000000907';

    private InvoiceMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvoiceMapper();
    }

    public function testAnInvoiceSurvivesTheRoundTrip(): void
    {
        $invoice = $this->invoice();

        $entity   = $this->mapper->toEntity($invoice);
        $lines    = [$this->mapper->lineToEntity($invoice->lines()[0], $invoice->id(), 0)];
        $payments = [$this->mapper->paymentToEntity(
            $invoice->payments()[0],
            $invoice->id(),
            $invoice->clinicId(),
            'EUR',
            0,
        )];

        $restored = $this->mapper->toDomain($entity, $lines, $payments);

        self::assertSame(self::INVOICE_ID, $restored->id()->toString());
        self::assertSame(self::CLINIC_ID, $restored->clinicId()->toString());
        self::assertSame(self::ADMISSION_ID, $restored->admissionId()?->toString());
        self::assertSame('FAC-2026-000003', $restored->number()?->toString());
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $restored->status());
        self::assertSame('Sophie Dupont', $restored->payer()->displayName());
        self::assertSame(self::CLIENT_ID, $restored->payer()->clientId()?->toString());
        self::assertSame(['12 rue des Lilas'], $restored->payer()->addressLines());
        self::assertSame('Merci de votre visite', $restored->notes());
        $discount = $restored->discount();
        self::assertNotNull($discount);
        self::assertSame(DiscountKind::PERCENTAGE, $discount->kind());
        self::assertSame('10', $discount->value());
        self::assertSame('Fidélité', $discount->reason());
        self::assertCount(1, $restored->lines());
        self::assertCount(1, $restored->payments());
        self::assertSame(2000, $restored->amountPaid()->minorUnits());
        self::assertSame('CONS_STD', $restored->lines()[0]->code());
        self::assertSame(self::USER_ID, $restored->payments()[0]->recordedByUserId());
        self::assertSame(CounterSalePayer::NEW_CLIENT, $restored->counterSalePayer());
        self::assertSame(CounterSalePayer::NEW_CLIENT, $entity->getCounterSalePayer());
        // The frozen amounts come back as they were issued.
        self::assertSame(5400, $restored->totalGross()->minorUnits());
        self::assertSame(500, $restored->discountAmount()->minorUnits());
        self::assertSame(6000, $restored->lines()[0]->grossAmount()->minorUnits());
        self::assertSame(PriceMode::INCLUDING_TAX, $restored->lines()[0]->priceMode());
        self::assertSame(OperationCategory::SERVICES, $restored->lines()[0]->operationCategory());
        self::assertSame('41.666667', $restored->lines()[0]->unitNet()->toDecimalString());
        self::assertSame([['rate' => '20', 'lineTotal' => 5000, 'allowance' => 500, 'vat' => 900]], $entity->getTaxBreakdown());
        self::assertSame(5000, $entity->getTotalNetBeforeAllowance());
        self::assertSame(500, $entity->getTotalAllowance());
        self::assertSame(4500, $entity->getTotalTaxBasis());
        self::assertSame(900, $entity->getTotalTax());
        self::assertSame(5400, $entity->getTotalGross());
    }

    public function testDecimalColumnsComeBackInTheirOriginalShape(): void
    {
        $entity = $this->mapper->toEntity($this->invoice());

        // MySQL pads DECIMAL to its scale; the value objects expect what they
        // were built with.
        $line = new InvoiceLineEntity();
        $line->setId(Uuid::fromString(self::LINE_ID));
        $line->setInvoiceId(Uuid::fromString(self::INVOICE_ID));
        $line->setLabel('Consultation');
        $line->setCode(null);
        $line->setQuantity('2.00');
        $line->setUnitPriceMinorUnits(5000);
        $line->setUnitPriceMode('excl');
        $line->setUnitNetMicroUnits(50_000_000);
        $line->setNetMinorUnits(10000);
        $line->setGrossMinorUnits(12000);
        $line->setTaxRatePercent('20.0000');
        $line->setTaxCategoryCode(null);
        $line->setSource(InvoiceLineSource::MANUAL);
        $line->setSourceReference(null);
        $line->setPosition(0);

        $restored = $this->mapper->toDomain($entity, [$line], []);

        self::assertSame('2', $restored->lines()[0]->quantity());
        self::assertSame('20', $restored->lines()[0]->taxRatePercent());
    }

    public function testAZeroDecimalIsNotTrimmedAway(): void
    {
        $entity = $this->mapper->toEntity($this->invoice());

        $line = new InvoiceLineEntity();
        $line->setId(Uuid::fromString(self::LINE_ID));
        $line->setInvoiceId(Uuid::fromString(self::INVOICE_ID));
        $line->setLabel('Remise');
        $line->setCode(null);
        $line->setQuantity('1');
        $line->setUnitPriceMinorUnits(0);
        $line->setUnitPriceMode('incl');
        $line->setNetMinorUnits(0);
        $line->setGrossMinorUnits(0);
        $line->setTaxRatePercent('0.0000');
        $line->setTaxCategoryCode(null);
        $line->setSource(InvoiceLineSource::ADJUSTMENT);
        $line->setSourceReference(null);
        $line->setPosition(0);

        $restored = $this->mapper->toDomain($entity, [$line], []);

        self::assertSame('0', $restored->lines()[0]->taxRatePercent());
        self::assertSame('1', $restored->lines()[0]->quantity());
    }

    public function testADraftWithoutNumberPayerOrDiscountMapsToNulls(): void
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: null,
            payer: Payer::anonymous(),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );

        $entity = $this->mapper->toEntity($invoice);

        self::assertNull($entity->getNumber());
        self::assertNull($entity->getAdmissionId());
        self::assertNull($entity->getPayerClientId());
        self::assertNull($entity->getDiscountKind());
        self::assertNull($entity->getDiscountValue());
        self::assertNull($entity->getDiscountReason());
        self::assertNull($entity->getNotes());
        self::assertSame([], $entity->getPayerAddressLines());

        $restored = $this->mapper->toDomain($entity, [], []);

        self::assertNull($restored->number());
        self::assertNull($restored->admissionId());
        self::assertNull($restored->discount());
        self::assertNull($restored->payer()->clientId());
    }

    public function testAnExistingRowIsUpdatedRatherThanReplaced(): void
    {
        $invoice = $this->invoice();
        $entity  = new InvoiceEntity();
        $entity->setId(Uuid::fromString(self::INVOICE_ID));
        $entity->setClinicId(Uuid::fromString(self::CLINIC_ID));
        $entity->setCreatedAt($this->now());

        $this->mapper->applyMutableState($invoice, $entity);

        self::assertSame('FAC-2026-000003', $entity->getNumber());
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $entity->getStatus());
        self::assertSame('Sophie Dupont', $entity->getPayerName());
        self::assertSame('EUR', $entity->getCurrency());
    }

    public function testAPaymentWithoutARecorderMapsToNull(): void
    {
        $payment = Payment::create(
            id: PaymentId::fromString(self::PAYMENT_ID),
            method: PaymentMethod::CASH,
            amount: Money::fromMinorUnits(500, CurrencyCode::fromString('EUR')),
            receivedAt: $this->now(),
        );

        $entity = $this->mapper->paymentToEntity(
            $payment,
            InvoiceId::fromString(self::INVOICE_ID),
            ClinicId::fromString(self::CLINIC_ID),
            'EUR',
            3,
        );

        self::assertNull($entity->getRecordedByUserId());
        self::assertNull($entity->getReference());
        self::assertSame(3, $entity->getPosition());
        self::assertSame(PaymentMethod::CASH, $entity->getMethod());
        self::assertSame(500, $entity->getAmountMinorUnits());
        self::assertSame('EUR', $entity->getCurrency());
        self::assertSame(self::INVOICE_ID, $entity->getInvoiceId()->toRfc4122());
        self::assertSame(self::CLINIC_ID, $entity->getClinicId()->toRfc4122());
    }

    public function testALineEntityCarriesEveryFrozenAmount(): void
    {
        $entity = $this->mapper->lineToEntity($this->invoice()->lines()[0], InvoiceId::fromString(self::INVOICE_ID), 2);

        self::assertSame(self::LINE_ID, $entity->getId()->toRfc4122());
        self::assertSame(self::INVOICE_ID, $entity->getInvoiceId()->toRfc4122());
        self::assertSame('Consultation standard', $entity->getLabel());
        self::assertSame('CONS_STD', $entity->getCode());
        self::assertSame('1', $entity->getQuantity());
        self::assertSame(6000, $entity->getUnitPriceMinorUnits());
        self::assertSame('incl', $entity->getUnitPriceMode());
        self::assertSame(41_666_667, $entity->getUnitNetMicroUnits());
        self::assertSame('services', $entity->getOperationCategory());
        self::assertSame(5000, $entity->getNetMinorUnits());
        self::assertSame(6000, $entity->getGrossMinorUnits());
        self::assertSame('20', $entity->getTaxRatePercent());
        self::assertSame('veterinary.act.consultation', $entity->getTaxCategoryCode());
        self::assertSame(InvoiceLineSource::CONSULTATION, $entity->getSource());
        self::assertSame('src-1', $entity->getSourceReference());
        self::assertSame(2, $entity->getPosition());
    }

    public function testTheEntityKeepsEveryTimestamp(): void
    {
        $entity = $this->mapper->toEntity($this->invoice());

        $stamp = $this->now()->format('Y-m-d H:i:s');

        self::assertSame($stamp, $entity->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame($stamp, $entity->getUpdatedAt()->format('Y-m-d H:i:s'));
        self::assertSame($stamp, $entity->getIssuedAt()?->format('Y-m-d H:i:s'));
        self::assertNull($entity->getPaidAt());
        self::assertNull($entity->getCancelledAt());
        self::assertNull($entity->getCancellationReason());
    }

    private function invoice(): Invoice
    {
        $eur = CurrencyCode::fromString('EUR');

        return Invoice::reconstitute(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            number: InvoiceNumber::of(2026, 3),
            status: InvoiceStatus::PARTIALLY_PAID,
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont', ['12 rue des Lilas']),
            lines: [
                InvoiceLine::reconstitute(
                    id: InvoiceLineId::fromString(self::LINE_ID),
                    label: 'Consultation standard',
                    code: 'CONS_STD',
                    quantity: '1',
                    enteredUnitPrice: Money::fromMinorUnits(6000, $eur),
                    priceMode: PriceMode::INCLUDING_TAX,
                    taxCategoryCode: 'veterinary.act.consultation',
                    taxRatePercent: '20',
                    operationCategory: OperationCategory::SERVICES,
                    source: InvoiceLineSource::CONSULTATION,
                    sourceReference: 'src-1',
                    amounts: LineAmounts::of(
                        Money::fromMinorUnits(5000, $eur),
                        Money::fromMinorUnits(6000, $eur),
                        UnitPrice::fromMicroUnits(41_666_667, $eur),
                    ),
                ),
            ],
            payments: [
                Payment::create(
                    id: PaymentId::fromString(self::PAYMENT_ID),
                    method: PaymentMethod::CARD,
                    amount: Money::fromMinorUnits(2000, $eur),
                    receivedAt: $this->now(),
                    reference: 'TX-9',
                    recordedByUserId: self::USER_ID,
                ),
            ],
            discount: Discount::percentage('10', 'Fidélité'),
            currency: $eur,
            notes: 'Merci de votre visite',
            createdAt: $this->now(),
            updatedAt: $this->now(),
            issuedAt: $this->now(),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
            counterSalePayer: CounterSalePayer::NEW_CLIENT,
            frozenBreakdown: [RateBucket::of('20', 5000, 500, 900, $eur)],
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
