<?php

declare(strict_types=1);

namespace App\Tests\Support\Billing;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Service\CreditNoteCalculator;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\BuyerTaxStatus;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * Issued professional (and private) documents for the electronic-invoicing
 * bridge tests: an admission invoice of « Médor » with notes, and its credit note.
 */
final class ProfessionalDocuments
{
    public const string INVOICE     = '01960000-0000-7000-8000-0000000fc001';
    public const string CLINIC      = '01960000-0000-7000-8000-0000000fc002';
    public const string CREDIT_NOTE = '01960000-0000-7000-8000-0000000fc0c1';
    public const string NOTES       = 'Médor : rappel dans trois semaines, propriétaire inquiet.';

    private static int $ids = 0;

    public static function issuedInvoice(): Invoice
    {
        $invoice = self::draft(self::professional());
        self::add($invoice, 'Vaccin CHPPiL', 6000, InvoiceLineSource::CONSULTATION, OperationCategory::SERVICES);
        self::add($invoice, 'Croquettes rénales', 4550, InvoiceLineSource::MANUAL, OperationCategory::GOODS, '5.5');
        $invoice->applyDiscount(Discount::percentage('10'), self::now());
        $invoice->setNotes(self::NOTES, self::now());
        $invoice->issue(InvoiceNumber::of(2026, 7), SellerIdentities::complete(), self::now(), 1000);

        return $invoice;
    }

    public static function privateInvoice(bool $issued = true): Invoice
    {
        $invoice = self::draft(Payer::create(PayerId::fromString('01960000-0000-7000-8000-0000000fc0a1'), 'Sophie Dupont'));
        self::add($invoice, 'Consultation', 5000);

        if ($issued) {
            $invoice->issue(InvoiceNumber::of(2026, 8), SellerIdentities::complete(), self::now());
        }

        return $invoice;
    }

    public static function professionalDraft(): Invoice
    {
        $invoice = self::draft(self::professional());
        self::add($invoice, 'Consultation', 5000);

        return $invoice;
    }

    public static function creditNoteOf(Invoice $invoice): CreditNote
    {
        $credit = new CreditNoteCalculator()->credit(
            $invoice,
            [$invoice->lines()[0]],
            [],
            static fn (): InvoiceLineId => InvoiceLineId::fromString(\sprintf('01960000-0000-7000-8000-0000000fd%03d', ++self::$ids % 1000)),
        );

        return CreditNote::issue(
            id: CreditNoteId::fromString(self::CREDIT_NOTE),
            clinicId: $invoice->clinicId(),
            number: InvoiceNumber::of(2026, 1, 'AV'),
            invoiceId: $invoice->id(),
            invoiceNumber: (string) $invoice->number()?->toString(),
            payer: $invoice->payer(),
            lines: $credit['lines'],
            breakdown: $credit['breakdown'],
            reason: 'Vaccin non administré',
            refundMethod: null,
            refunded: Money::fromMinorUnits(0, $invoice->currency()),
            currency: $invoice->currency(),
            now: self::now()->modify('+1 day'),
            processingRule: $invoice->processingRule(),
            framework: $invoice->framework(),
            invoiceIssuedAt: $invoice->issuedAt(),
        );
    }

    public static function professional(): Payer
    {
        $siren = Siren::fromInput('523456788');

        return Payer::create(
            PayerId::fromString('01960000-0000-7000-8000-0000000fc0a2'),
            'EARL Élevage des Calanques',
            ['Chemin des Goudes', '13008 Marseille'],
            'Mme Claire Rostand',
            BuyerFiscalIdentity::of(
                BuyerTaxStatus::FRENCH_BUSINESS,
                'EARL Élevage des Calanques',
                $siren,
                FrenchVatNumber::forSiren($siren),
                ['Chemin des Goudes'],
                '13008',
                'Marseille',
            ),
        );
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 10:00:00');
    }

    private static function draft(Payer $payer): Invoice
    {
        return Invoice::draft(
            InvoiceId::fromString(self::INVOICE),
            ClinicId::fromString(self::CLINIC),
            AdmissionId::fromString('01960000-0000-7000-8000-0000000fc003'),
            $payer,
            CurrencyCode::fromString('EUR'),
            self::now(),
        );
    }

    private static function add(
        Invoice $invoice,
        string $label,
        int $priceInclTax,
        InvoiceLineSource $source = InvoiceLineSource::MANUAL,
        OperationCategory $nature = OperationCategory::SERVICES,
        string $rate = '20',
    ): void {
        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString(\sprintf('01960000-0000-7000-8000-0000000fb%03d', ++self::$ids % 1000)),
            label: $label,
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits($priceInclTax, CurrencyCode::fromString('EUR')),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: null,
            taxRatePercent: $rate,
            operationCategory: $nature,
            source: $source,
        ), self::now());
    }
}
