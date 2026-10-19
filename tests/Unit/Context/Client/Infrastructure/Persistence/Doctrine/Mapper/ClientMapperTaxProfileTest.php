<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientMapperTaxProfileTest extends TestCase
{
    /**
     * @return iterable<string, array{ClientTaxProfile, ClientTaxStatus, ?string, ?string, ?string}>
     */
    public static function provideTheTaxProfileSurvivesARoundTripCases(): iterable
    {
        $siren = Siren::fromInput('523456788');

        yield 'plain individual' => [ClientTaxProfile::default(), ClientTaxStatus::INDIVIDUAL, null, null, null];
        yield 'association' => [ClientTaxProfile::individual('Refuge des Collines'), ClientTaxStatus::INDIVIDUAL, 'Refuge des Collines', null, null];
        yield 'business without VAT' => [ClientTaxProfile::frenchBusiness('Centre équestre du Lac', Siren::fromInput('812345676'), null), ClientTaxStatus::FRENCH_BUSINESS, 'Centre équestre du Lac', '812345676', null];
        yield 'business with VAT' => [ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, FrenchVatNumber::forSiren($siren)), ClientTaxStatus::FRENCH_BUSINESS, 'Élevage des Calanques', '523456788', 'FR31523456788'];
    }

    #[DataProvider('provideTheTaxProfileSurvivesARoundTripCases')]
    public function testTheTaxProfileSurvivesARoundTrip(
        ClientTaxProfile $profile,
        ClientTaxStatus $status,
        ?string $legalName,
        ?string $siren,
        ?string $vatNumber,
    ): void {
        $mapper = new ClientMapper();
        $client = Client::create(
            ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            new ClientIdentity('Claire', 'Rostand'),
            [ContactMethod::email(EmailAddress::fromString('claire@example.fr'), ContactLabel::WORK, true)],
            new \DateTimeImmutable('2026-10-17 10:00:00'),
            $profile,
        );

        $mapped = $mapper->toEntity($client);
        $entity = $mapped['client'];

        self::assertSame($status, $entity->getTaxStatus());
        self::assertSame($legalName, $entity->getLegalName());
        self::assertSame($siren, $entity->getSiren());
        self::assertSame($vatNumber, $entity->getVatNumber());

        $restored = $mapper->toDomain($entity, $mapped['contactMethods']);

        self::assertTrue($restored->taxProfile()->equals($profile));
    }
}
