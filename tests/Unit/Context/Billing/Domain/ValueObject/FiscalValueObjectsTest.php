<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Application\Port\PayerFiscalSnapshotDto;
use App\Context\Billing\Application\Port\PayerSnapshotDto;
use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\BuyerTaxStatus;
use App\Context\Billing\Domain\ValueObject\FrameworkCase;
use App\Context\Billing\Domain\ValueObject\FrameworkNature;
use App\Context\Billing\Domain\ValueObject\InvoicingFramework;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PaymentTerms;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use App\Tests\Support\Billing\SellerIdentities;
use PHPUnit\Framework\TestCase;

final class FiscalValueObjectsTest extends TestCase
{
    public function testABusinessIdentityProposesItsSirenAsElectronicAddress(): void
    {
        $identity = $this->identity();

        self::assertTrue($identity->isFrenchBusiness());
        self::assertSame(BuyerTaxStatus::FRENCH_BUSINESS, $identity->taxStatus());
        self::assertSame('EARL Élevage des Calanques', $identity->legalName());
        self::assertSame('523456788', $identity->siren()?->value());
        self::assertSame('FR31523456788', $identity->vatNumber()?->value());
        self::assertSame('0225', $identity->electronicAddressScheme());
        self::assertSame('523456788', $identity->electronicAddress());
        self::assertSame(['Chemin des Goudes'], $identity->streetLines());
        self::assertSame('13008', $identity->postalCode());
        self::assertSame('Marseille', $identity->city());
        self::assertSame('FR', $identity->countryCode());
        self::assertTrue($identity->hasCompleteAddress());
        self::assertTrue($identity->equals($this->identity()));
    }

    public function testBlankFieldsAreDroppedAndNoSirenMeansNoElectronicAddress(): void
    {
        $identity = BuyerFiscalIdentity::of(BuyerTaxStatus::INDIVIDUAL, '  ', null, null, ['  ', 'Rue'], ' ', null, ' fr ');

        self::assertFalse($identity->isFrenchBusiness());
        self::assertNull($identity->legalName());
        self::assertNull($identity->electronicAddressScheme());
        self::assertNull($identity->electronicAddress());
        self::assertSame(['Rue'], $identity->streetLines());
        self::assertNull($identity->postalCode());
        self::assertSame('FR', $identity->countryCode());
        self::assertFalse($identity->hasCompleteAddress());
        self::assertFalse($identity->equals($this->identity()));
    }

    public function testThePayerComparesItsFiscalIdentityToo(): void
    {
        $withIdentity = Payer::create(null, 'EARL', [], null, $this->identity());

        self::assertTrue($withIdentity->isFrenchBusiness());
        self::assertTrue($withIdentity->equals(Payer::create(null, 'EARL', [], null, $this->identity())));
        self::assertFalse($withIdentity->equals(Payer::create(null, 'EARL')));
        self::assertFalse(Payer::create(null, 'EARL')->equals($withIdentity));
        self::assertTrue(Payer::create(null, 'EARL')->equals(Payer::create(null, 'EARL')));
        self::assertFalse(Payer::anonymous()->isFrenchBusiness());
    }

    public function testASnapshotBecomesAPayerWithItsIdentity(): void
    {
        $payer = $this->snapshot('523456788', 'FR31523456788')->toPayer();

        self::assertSame('01960000-0000-7000-8000-0000000fc001', $payer->clientId()?->toString());
        self::assertSame('FR31523456788', $payer->fiscalIdentity()?->vatNumber()?->value());
        self::assertSame(['Chemin des Goudes'], $payer->fiscalIdentity()->streetLines());
    }

    public function testIdentifiersThatNoLongerCheckOutAreDropped(): void
    {
        self::assertNull($this->snapshot('523456789', 'FR31523456788')->toPayer()->fiscalIdentity()?->siren());

        $wrongVat = $this->snapshot('523456788', 'FR00523456788')->toPayer()->fiscalIdentity();
        self::assertNotNull($wrongVat);
        self::assertSame('523456788', $wrongVat->siren()?->value());
        self::assertNull($wrongVat->vatNumber());
    }

    public function testAPrivateClientsSnapshotHasNoIdentity(): void
    {
        $snapshot = new PayerSnapshotDto(null, 'Sophie Dupont', [], null, new PayerFiscalSnapshotDto('individual', null, null, null, [], null, null));

        self::assertNull($snapshot->toPayer()->fiscalIdentity());
        self::assertNull($snapshot->toPayer()->clientId());
        self::assertNull(new PayerSnapshotDto(null, 'Sophie Dupont')->toPayer()->fiscalIdentity());
    }

    public function testTheFrameworkCodeReadsBothWays(): void
    {
        $framework = InvoicingFramework::fromCode('M1');

        self::assertSame(FrameworkNature::MIXED, $framework->nature);
        self::assertSame(FrameworkCase::TO_PAY, $framework->case);
        self::assertSame('M1', $framework->code());
        self::assertTrue($framework->equals(InvoicingFramework::of(FrameworkNature::MIXED, FrameworkCase::TO_PAY)));
        self::assertFalse($framework->equals(InvoicingFramework::of(FrameworkNature::MIXED, FrameworkCase::ALREADY_PAID)));
    }

    public function testAnUnknownFrameworkCodeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InvoicingFramework::fromCode('S7');
    }

    public function testPaymentTerms(): void
    {
        $terms = PaymentTerms::dueIn(45, new \DateTimeImmutable('2026-10-21 18:30'));

        self::assertSame('2026-12-05', $terms->dueDate?->format('Y-m-d'));
        self::assertSame('Paiement à 45 jours', $terms->note);
        self::assertSame('Facture acquittée', PaymentTerms::settled()->note);
        self::assertNull(PaymentTerms::reconstitute(null, 'x')->dueDate);
        self::assertArrayHasKey('PMT', PaymentTerms::PROFESSIONAL_MENTIONS);
    }

    public function testNegativePaymentTermsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentTerms::dueIn(-1, new \DateTimeImmutable());
    }

    public function testTheSellerFallsBackOnThirtyDays(): void
    {
        self::assertSame(30, SellerIdentities::complete(null)->paymentTermsDays());
        self::assertSame(60, SellerIdentities::complete(60)->paymentTermsDays());
        self::assertTrue(SellerIdentities::complete()->hasCompleteAddress());
        self::assertFalse(SellerIdentities::unknown()->hasCompleteAddress());
    }

    private function snapshot(string $siren, string $vat): PayerSnapshotDto
    {
        return new PayerSnapshotDto(
            '01960000-0000-7000-8000-0000000fc001',
            'EARL Élevage des Calanques',
            ['Chemin des Goudes', '13008 Marseille'],
            'Mme Claire Rostand',
            new PayerFiscalSnapshotDto('french_business', 'EARL Élevage des Calanques', $siren, $vat, ['Chemin des Goudes'], '13008', 'Marseille'),
        );
    }

    private function identity(): BuyerFiscalIdentity
    {
        $siren = Siren::fromInput('523456788');

        return BuyerFiscalIdentity::of(
            BuyerTaxStatus::FRENCH_BUSINESS,
            'EARL Élevage des Calanques',
            $siren,
            FrenchVatNumber::forSiren($siren),
            ['Chemin des Goudes'],
            '13008',
            'Marseille',
        );
    }
}
