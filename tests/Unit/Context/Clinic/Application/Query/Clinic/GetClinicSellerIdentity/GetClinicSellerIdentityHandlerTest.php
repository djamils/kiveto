<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\ClinicSellerIdentityDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\GetClinicSellerIdentity;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\GetClinicSellerIdentityHandler;
use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetClinicSellerIdentityHandler::class)]
#[CoversClass(GetClinicSellerIdentity::class)]
#[CoversClass(ClinicSellerIdentityDto::class)]
final class GetClinicSellerIdentityHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000102';

    public function testTheSellerIsReadFromTheLetterheadAndTheBillingSettings(): void
    {
        $at     = new \DateTimeImmutable('2026-10-01T10:00:00Z');
        $clinic = self::clinic();
        $clinic->updateLetterhead(Letterhead::create(
            legalName: 'Réseau VetoFrance — Établissement de Cassis',
            legalForm: 'SAS',
            siret: '90112233300023',
            vatNumber: 'FR00901122333',
            address: PostalAddress::create('5 avenue de la Marne', 'Cassis', 'FR', postalCode: '13260'),
        ), $at);
        $clinic->updateBillingSettings(BillingSettings::create(invoiceNumberPrefix: 'CAS', creditNotePrefix: 'AVCAS', professionalPaymentTermsDays: 30), $at);

        $seller = $this->handler($clinic)(new GetClinicSellerIdentity(self::CLINIC_ID));

        self::assertNotNull($seller);
        self::assertSame(self::CLINIC_ID, $seller->clinicId);
        self::assertSame('Réseau VetoFrance — Établissement de Cassis', $seller->legalName);
        self::assertSame('901122333', $seller->siren);
        self::assertSame('FR00901122333', $seller->vatNumber);
        self::assertSame(['5 avenue de la Marne'], $seller->streetLines);
        self::assertSame('13260', $seller->postalCode);
        self::assertSame('Cassis', $seller->city);
        self::assertSame('FR', $seller->countryCode);
        self::assertSame('SAS', $seller->legalSentence);
        self::assertSame(30, $seller->professionalPaymentTermsDays);
        self::assertNull($seller->iban);
        self::assertNull($seller->bic);
        self::assertSame('CAS', $seller->invoicePrefix);
        self::assertSame('AVCAS', $seller->creditNotePrefix);
        self::assertTrue($seller->numberingIsUnique);
    }

    public function testAClinicWithNoAddressIsLocatedInItsCountry(): void
    {
        $seller = $this->handler(self::clinic())(new GetClinicSellerIdentity(self::CLINIC_ID));

        self::assertNotNull($seller);
        self::assertSame('Clinique des Calanques', $seller->legalName);
        self::assertSame([], $seller->streetLines);
        self::assertNull($seller->city);
        self::assertSame('FR', $seller->countryCode);
        self::assertNull($seller->siren);
        self::assertSame('FAC', $seller->invoicePrefix);
    }

    public function testAnUnknownClinicHasNoSellerIdentity(): void
    {
        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        self::assertNull((new GetClinicSellerIdentityHandler($repository, new InvoiceNumberingUniqueness($repository)))(new GetClinicSellerIdentity(self::CLINIC_ID)));
    }

    private function handler(Clinic $clinic): GetClinicSellerIdentityHandler
    {
        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);
        $repository->method('findOthersWithSiren')->willReturn([]);

        return new GetClinicSellerIdentityHandler($repository, new InvoiceNumberingUniqueness($repository));
    }

    private static function clinic(): Clinic
    {
        return Clinic::create(
            ClinicId::fromString(self::CLINIC_ID),
            'Clinique des Calanques',
            ClinicSlug::fromString('clinic-calanques'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
    }
}
