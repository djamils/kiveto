<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidBillingSettingsException;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\TestCase;

final class BillingSettingsTest extends TestCase
{
    public function testAPrefixIsStoredTheWayItPrints(): void
    {
        self::assertSame('FAC', BillingSettings::create(invoiceNumberPrefix: 'fac')->invoiceNumberPrefix);
        self::assertSame('F/2026', BillingSettings::create(invoiceNumberPrefix: 'f/2026')->invoiceNumberPrefix);
    }

    public function testDocumentsAreNumberedUnderTheDefaultPrefixesWhenTheClinicChoseNone(): void
    {
        self::assertSame('FAC', BillingSettings::empty()->effectiveInvoicePrefix());
        self::assertSame('AV', BillingSettings::empty()->effectiveCreditNotePrefix());

        $chosen = BillingSettings::create(invoiceNumberPrefix: 'lyo', creditNotePrefix: 'avlyo');

        self::assertSame('LYO', $chosen->effectiveInvoicePrefix());
        self::assertSame('AVLYO', $chosen->effectiveCreditNotePrefix());
    }

    public function testAPrefixWithSpacesOrPunctuationIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(invoiceNumberPrefix: 'FAC 2026 !');
    }

    public function testAnIbanLosesItsSpacesAndKeepsItsCase(): void
    {
        $settings = BillingSettings::create(iban: 'fr76 3000 6000 0112 3456 7890 189');

        self::assertSame('FR7630006000011234567890189', $settings->iban);
    }

    public function testSomethingThatIsNotAnIbanIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(iban: 'mon compte à la banque');
    }

    public function testSomethingThatIsNotABicIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(bic: 'BIC!');
    }

    public function testABicOfEightOrElevenCharactersIsAccepted(): void
    {
        self::assertSame('AGRIFRPP', BillingSettings::create(bic: 'agrifrpp')->bic);
        self::assertSame('AGRIFRPP123', BillingSettings::create(bic: 'AGRIFRPP123')->bic);
    }

    public function testPaymentTermsStayWithinAYear(): void
    {
        self::assertSame(0, BillingSettings::create(paymentTermsDays: 0)->paymentTermsDays);
        self::assertSame(365, BillingSettings::create(paymentTermsDays: 365)->paymentTermsDays);

        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(paymentTermsDays: 366);
    }

    public function testNegativeTermsAreRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(paymentTermsDays: -1);
    }

    public function testBlankFieldsAreStoredAsNothing(): void
    {
        $settings = BillingSettings::create(invoiceFooterNote: '   ', iban: '', bic: null);

        self::assertNull($settings->invoiceFooterNote);
        self::assertNull($settings->iban);
        self::assertTrue($settings->isEmpty());
    }

    public function testTwoIdenticalSettingsAreEqual(): void
    {
        $address = PostalAddress::create(streetLine1: '3 avenue du Siège', city: 'Lyon', countryCode: 'FR', postalCode: '69002');

        $one = BillingSettings::create(billingAddress: $address, invoiceNumberPrefix: 'FAC', paymentTermsDays: 30);
        $two = BillingSettings::create(billingAddress: $address, invoiceNumberPrefix: 'FAC', paymentTermsDays: 30);

        self::assertTrue($one->equals($two));
        self::assertFalse($one->equals(BillingSettings::create(invoiceNumberPrefix: 'FAC', paymentTermsDays: 30)));
    }
}
