<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\ValueObject;

use App\Context\Client\Domain\Exception\InvalidClientTaxProfileException;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\TestCase;

final class ClientTaxProfileTest extends TestCase
{
    public function testDefaultIsAnIndividualWithoutLegalName(): void
    {
        $profile = ClientTaxProfile::default();

        self::assertSame(ClientTaxStatus::INDIVIDUAL, $profile->status());
        self::assertNull($profile->legalName());
        self::assertNull($profile->siren());
        self::assertNull($profile->vatNumber());
        self::assertFalse($profile->hasLegalName());
    }

    public function testAnIndividualMayCarryALegalName(): void
    {
        $profile = ClientTaxProfile::individual('  Refuge des Collines ');

        self::assertSame(ClientTaxStatus::INDIVIDUAL, $profile->status());
        self::assertSame('Refuge des Collines', $profile->legalName());
        self::assertTrue($profile->hasLegalName());
        self::assertNull(ClientTaxProfile::individual('   ')->legalName());
    }

    public function testAFrenchBusinessCarriesItsIdentifiers(): void
    {
        $siren   = Siren::fromInput('523456788');
        $vat     = FrenchVatNumber::forSiren($siren);
        $profile = ClientTaxProfile::frenchBusiness('Élevage des Calanques', $siren, $vat);

        self::assertSame(ClientTaxStatus::FRENCH_BUSINESS, $profile->status());
        self::assertSame('Élevage des Calanques', $profile->legalName());
        self::assertSame($siren, $profile->siren());
        self::assertSame($vat, $profile->vatNumber());
    }

    public function testAFrenchBusinessNeedsALegalName(): void
    {
        try {
            ClientTaxProfile::frenchBusiness(' ', Siren::fromInput('523456788'), null);
            self::fail('Expected an InvalidClientTaxProfileException.');
        } catch (InvalidClientTaxProfileException $exception) {
            self::assertSame(InvalidClientTaxProfileException::LEGAL_NAME_REQUIRED, $exception->reason);
        }
    }

    public function testTheLegalNameIsCappedAt255Characters(): void
    {
        self::assertSame(255, mb_strlen((string) ClientTaxProfile::individual(str_repeat('é', 255))->legalName()));

        $this->expectException(InvalidClientTaxProfileException::class);
        $this->expectExceptionMessage('legal_name_too_long');

        ClientTaxProfile::individual(str_repeat('é', 256));
    }

    public function testEquality(): void
    {
        $siren = Siren::fromInput('523456788');
        $a     = ClientTaxProfile::frenchBusiness('Calanques', $siren, null);

        self::assertTrue($a->equals(ClientTaxProfile::frenchBusiness('Calanques', Siren::fromInput('523 456 788'), null)));
        self::assertFalse($a->equals(ClientTaxProfile::frenchBusiness('Calanques', $siren, FrenchVatNumber::forSiren($siren))));
        self::assertFalse($a->equals(ClientTaxProfile::frenchBusiness('Calanques', Siren::fromInput('812345676'), null)));
        self::assertFalse($a->equals(ClientTaxProfile::frenchBusiness('Autre', $siren, null)));
        self::assertFalse($a->equals(ClientTaxProfile::individual('Calanques')));
        self::assertTrue(ClientTaxProfile::default()->equals(ClientTaxProfile::individual(null)));
    }

    public function testStatusLabels(): void
    {
        self::assertSame('Particulier ou structure non assujettie', ClientTaxStatus::INDIVIDUAL->label());
        self::assertSame('Professionnel assujetti à la TVA', ClientTaxStatus::FRENCH_BUSINESS->label());
    }
}
