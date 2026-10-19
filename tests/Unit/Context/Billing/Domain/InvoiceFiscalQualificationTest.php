<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Exception\InvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Exception\InvoiceNotTransmissibleException;
use App\Context\Billing\Domain\Exception\IssuedProfessionalInvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Policy\ElectronicInvoiceReadiness;
use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\BuyerTaxStatus;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceTypeCode;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Context\Billing\Domain\ValueObject\ReadinessRequirement;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Tests\Support\Billing\SellerIdentities;
use PHPUnit\Framework\TestCase;

final class InvoiceFiscalQualificationTest extends TestCase
{
    private int $ids = 0;

    public function testAPrivateClientsInvoiceIsB2cAndCalculatedTaxIncludedFirst(): void
    {
        $invoice = $this->draft(Payer::anonymous());

        self::assertSame(ProcessingRule::B2C, $invoice->processingRule());
        self::assertSame(CalculationMode::INCLUDING_TAX_FIRST, $invoice->calculationMode());
        self::assertSame(InvoiceTypeCode::COMMERCIAL_INVOICE, $invoice->typeCode());
    }

    public function testAProfessionalsDraftIsB2bAndCalculatedTaxExcludedFirst(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 1000, OperationCategory::SERVICES);
        $this->add($invoice, 1000, OperationCategory::SERVICES);
        $this->add($invoice, 1000, OperationCategory::SERVICES);

        // AC 1: 3 × 10.00 € entered VAT included, B2B → 24.99 + 5.00.
        self::assertSame(ProcessingRule::B2B, $invoice->processingRule());
        self::assertSame(2499, $invoice->totalNet()->minorUnits());
        self::assertSame(500, $invoice->totalTax()->minorUnits());
        self::assertSame(2999, $invoice->totalGross()->minorUnits());
    }

    public function testChangingThePayerRecalculatesTheDraft(): void
    {
        $invoice = $this->draft(Payer::anonymous());
        $this->add($invoice, 1000, OperationCategory::SERVICES);
        $this->add($invoice, 1000, OperationCategory::SERVICES);
        $this->add($invoice, 1000, OperationCategory::SERVICES);

        self::assertSame(3000, $invoice->totalGross()->minorUnits());

        $invoice->changePayer($this->professional(), $this->now());

        self::assertSame(2999, $invoice->totalGross()->minorUnits());
    }

    public function testActsOnlyPaidAtTheCounterAreFramedS2AndSettled(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 4500, OperationCategory::SERVICES);
        $invoice->issue(InvoiceNumber::of(2026, 1), SellerIdentities::complete(), $this->now(), $invoice->totalGross()->minorUnits());

        self::assertSame('S2', $invoice->framework()?->code());
        self::assertNull($invoice->paymentTerms()?->dueDate);
        self::assertSame('Facture acquittée', $invoice->paymentTerms()?->note);

        $issued = array_values(array_filter($invoice->pullDomainEvents(), static fn (object $e): bool => $e instanceof InvoiceIssued));
        self::assertSame('B2B', $issued[0]->processingRule);
    }

    public function testGoodsLeftToPayAreFramedB1WithADueDate(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 6499, OperationCategory::GOODS);
        $invoice->issue(InvoiceNumber::of(2026, 2), SellerIdentities::complete(30), $this->now());

        self::assertSame('B1', $invoice->framework()?->code());
        $terms = $invoice->paymentTerms();
        self::assertNotNull($terms);
        self::assertSame('2026-11-20', $terms->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 30 jours', $terms->note);
    }

    public function testTheDefaultPaymentTermsAreThirtyDays(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 6499, OperationCategory::GOODS);
        $invoice->issue(InvoiceNumber::of(2026, 2), SellerIdentities::complete(null), $this->now(), 100);

        self::assertSame('B1', $invoice->framework()?->code());
        self::assertSame('Paiement à 30 jours', $invoice->paymentTerms()?->note);
    }

    public function testActsAndGoodsPaidAreFramedM2(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 4500, OperationCategory::SERVICES);
        $this->add($invoice, 1290, OperationCategory::GOODS);
        $invoice->issue(InvoiceNumber::of(2026, 3), SellerIdentities::complete(), $this->now(), $invoice->totalGross()->minorUnits());

        self::assertSame('M2', $invoice->framework()?->code());
    }

    public function testTheProcessingRuleIsFrozenAtIssue(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 4500, OperationCategory::SERVICES);
        $invoice->issue(InvoiceNumber::of(2026, 4), SellerIdentities::complete(), $this->now());

        self::assertSame(ProcessingRule::B2B, $invoice->processingRule());
        self::assertSame(CalculationMode::EXCLUDING_TAX_FIRST, $invoice->calculationMode());
    }

    public function testAnIssuedProfessionalInvoiceCannotBeCancelled(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 4500, OperationCategory::SERVICES);
        $invoice->issue(InvoiceNumber::of(2026, 5), SellerIdentities::complete(), $this->now());

        $this->expectException(IssuedProfessionalInvoiceCannotBeCancelledException::class);

        $invoice->cancel('Erreur', $this->now());
    }

    public function testAProfessionalDraftCanStillBeCancelledLikeAnyDraft(): void
    {
        $invoice = $this->draft($this->professional());

        $this->expectException(InvoiceCannotBeCancelledException::class);

        // Not refused for being professional: only the missing reason stops it.
        $invoice->cancel('  ', $this->now());
    }

    public function testAPrivateInvoiceIsCancelledAsBefore(): void
    {
        $invoice = $this->draft(Payer::anonymous());
        $this->add($invoice, 4500, OperationCategory::SERVICES);
        $invoice->issue(InvoiceNumber::of(2026, 6), SellerIdentities::complete(), $this->now());
        $invoice->cancel('Erreur de saisie', $this->now());

        self::assertSame('Erreur de saisie', $invoice->cancellationReason());
    }

    public function testAPrivateDraftHasNoReadinessRequirement(): void
    {
        $invoice = $this->draft(Payer::anonymous());
        $this->add($invoice, 4500, null);

        self::assertSame([], new ElectronicInvoiceReadiness()->check($invoice, SellerIdentities::unknown()));
    }

    public function testACompleteProfessionalDraftIsReady(): void
    {
        $invoice = $this->draft($this->professional());
        $this->add($invoice, 4500, OperationCategory::SERVICES);

        self::assertSame([], new ElectronicInvoiceReadiness()->check($invoice, SellerIdentities::complete()));
        new ElectronicInvoiceReadiness()->assertReady($invoice, SellerIdentities::complete());
        $this->addToAssertionCount(1);
    }

    public function testReadinessListsEverythingThatIsMissing(): void
    {
        $buyer = Payer::create(
            PayerId::fromString('01960000-0000-7000-8000-0000000fa001'),
            'Jean Martin',
            [],
            null,
            BuyerFiscalIdentity::of(BuyerTaxStatus::FRENCH_BUSINESS, null, null, null, [], null, null),
        );
        $invoice = $this->draft($buyer);
        $this->add($invoice, 4500, null);
        $this->add($invoice, 4500, OperationCategory::GOODS, '0');

        $seller = new SellerIdentity(' ', null, null, ['1 rue'], null, 'Paris', 'FR', null, null, null, null, false);

        self::assertSame([
            ReadinessRequirement::BUYER_LEGAL_NAME,
            ReadinessRequirement::BUYER_SIREN,
            ReadinessRequirement::BUYER_ADDRESS,
            ReadinessRequirement::LINE_TAX_CATEGORY,
            ReadinessRequirement::LINE_ZERO_RATE,
            ReadinessRequirement::SELLER_SIREN,
            ReadinessRequirement::SELLER_VAT,
            ReadinessRequirement::SELLER_ADDRESS,
            ReadinessRequirement::SELLER_LEGAL_NAME,
            ReadinessRequirement::SELLER_NUMBERING_NOT_UNIQUE,
        ], new ElectronicInvoiceReadiness()->check($invoice, $seller));
    }

    public function testAssertingReadinessThrowsWithTheMissingRequirements(): void
    {
        $invoice = $this->draft($this->professional(address: false));
        $this->add($invoice, 4500, OperationCategory::SERVICES);

        try {
            new ElectronicInvoiceReadiness()->assertReady($invoice, SellerIdentities::complete());
            self::fail('Expected the draft to be refused.');
        } catch (InvoiceNotTransmissibleException $e) {
            self::assertSame([ReadinessRequirement::BUYER_ADDRESS], $e->requirements);
            self::assertSame($invoice->id()->toString(), $e->invoiceId);
            self::assertStringContainsString('buyer_address', $e->getMessage());
        }
    }

    private function professional(bool $address = true): Payer
    {
        $siren = Siren::fromInput('523456788');

        return Payer::create(
            PayerId::fromString('01960000-0000-7000-8000-0000000fa002'),
            'EARL Élevage des Calanques',
            $address ? ['Chemin des Goudes', '13008 Marseille'] : [],
            'Mme Claire Rostand',
            BuyerFiscalIdentity::of(
                BuyerTaxStatus::FRENCH_BUSINESS,
                'EARL Élevage des Calanques',
                $siren,
                FrenchVatNumber::forSiren($siren),
                $address ? ['Chemin des Goudes'] : [],
                $address ? '13008' : null,
                $address ? 'Marseille' : null,
            ),
        );
    }

    private function draft(Payer $payer): Invoice
    {
        return Invoice::draft(
            InvoiceId::fromString('01960000-0000-7000-8000-0000000fa010'),
            ClinicId::fromString('01960000-0000-7000-8000-0000000fa011'),
            null,
            $payer,
            CurrencyCode::fromString('EUR'),
            $this->now(),
        );
    }

    private function add(Invoice $invoice, int $priceInclTax, ?OperationCategory $nature, string $rate = '20'): void
    {
        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString(\sprintf('01960000-0000-7000-8000-0000000fb%03d', ++$this->ids)),
            label: 'Ligne',
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits($priceInclTax, CurrencyCode::fromString('EUR')),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: null,
            taxRatePercent: null === $nature ? '0' : $rate,
            operationCategory: $nature,
            source: InvoiceLineSource::MANUAL,
        ), $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 10:00:00');
    }
}
