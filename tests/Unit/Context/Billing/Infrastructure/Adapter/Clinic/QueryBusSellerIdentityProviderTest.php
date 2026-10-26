<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Clinic;

use App\Context\Billing\Infrastructure\Adapter\Clinic\QueryBusSellerIdentityProvider;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\ClinicSellerIdentityDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\GetClinicSellerIdentity;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;

final class QueryBusSellerIdentityProviderTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000fd001';

    public function testTheClinicBecomesTheSeller(): void
    {
        $seller = $this->provider($this->dto('812456903', 'FR20812456903'))->forClinic(self::CLINIC_ID);

        self::assertSame('SELARL Clinique du Parc', $seller->legalName);
        self::assertSame('812456903', $seller->siren?->value());
        self::assertSame('FR20812456903', $seller->vatNumber?->value());
        self::assertSame(['18 rue des Abbesses'], $seller->streetLines);
        self::assertSame('75018', $seller->postalCode);
        self::assertSame('Paris', $seller->city);
        self::assertSame('FR', $seller->countryCode);
        self::assertSame('SELARL au capital de 30 000 €', $seller->legalSentence);
        self::assertSame(45, $seller->professionalPaymentTermsDays);
        self::assertSame('FR76', $seller->iban);
        self::assertSame('AGRIFRPP', $seller->bic);
        self::assertFalse($seller->numberingIsUnique);
    }

    public function testIdentifiersThatDoNotCheckOutAreDropped(): void
    {
        $badSiren = $this->provider($this->dto('812456904', 'FR20812456903'))->forClinic(self::CLINIC_ID);
        self::assertNull($badSiren->siren);
        self::assertNull($badSiren->vatNumber);

        $badVat = $this->provider($this->dto('812456903', 'FR00812456903'))->forClinic(self::CLINIC_ID);
        self::assertSame('812456903', $badVat->siren?->value());
        self::assertNull($badVat->vatNumber);

        $none = $this->provider($this->dto(null, null))->forClinic(self::CLINIC_ID);
        self::assertNull($none->siren);
    }

    public function testAnUnknownClinicIsAnEmptySeller(): void
    {
        $seller = $this->provider(null)->forClinic(self::CLINIC_ID);

        self::assertSame('', $seller->legalName);
        self::assertNull($seller->siren);
        self::assertFalse($seller->hasCompleteAddress());
    }

    private function provider(?ClinicSellerIdentityDto $dto): QueryBusSellerIdentityProvider
    {
        $bus = $this->createMock(QueryBusInterface::class);
        $bus->expects(self::once())->method('ask')
            ->with(self::callback(static fn (object $q): bool => $q instanceof GetClinicSellerIdentity && self::CLINIC_ID === $q->clinicId))
            ->willReturn($dto)
        ;

        return new QueryBusSellerIdentityProvider($bus);
    }

    private function dto(?string $siren, ?string $vat): ClinicSellerIdentityDto
    {
        return new ClinicSellerIdentityDto(
            clinicId: self::CLINIC_ID,
            legalName: 'SELARL Clinique du Parc',
            siren: $siren,
            vatNumber: $vat,
            streetLines: ['18 rue des Abbesses'],
            postalCode: '75018',
            city: 'Paris',
            countryCode: 'FR',
            legalSentence: 'SELARL au capital de 30 000 €',
            professionalPaymentTermsDays: 45,
            iban: 'FR76',
            bic: 'AGRIFRPP',
            invoicePrefix: 'PAR',
            creditNotePrefix: 'AVPAR',
            numberingIsUnique: false,
        );
    }
}
