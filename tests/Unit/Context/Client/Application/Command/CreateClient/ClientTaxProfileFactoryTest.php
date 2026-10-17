<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileFactory;
use App\Context\Client\Domain\Exception\InvalidClientTaxProfileException;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Shared\Domain\ValueObject\InvalidFrenchVatNumberException;
use App\Shared\Domain\ValueObject\InvalidSirenException;
use PHPUnit\Framework\TestCase;

final class ClientTaxProfileFactoryTest extends TestCase
{
    public function testAnIndividualKeepsItsLegalNameAndIgnoresIdentifiers(): void
    {
        $profile = ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('individual', 'Refuge des Collines', '523456788', 'FR31523456788'));

        self::assertSame(ClientTaxStatus::INDIVIDUAL, $profile->status());
        self::assertSame('Refuge des Collines', $profile->legalName());
        self::assertNull($profile->siren());
        self::assertNull($profile->vatNumber());
    }

    public function testAFrenchBusinessIsNormalised(): void
    {
        $profile = ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('french_business', 'Élevage des Calanques', '523 456 788 00012', 'fr 31 523456788'));

        self::assertSame(ClientTaxStatus::FRENCH_BUSINESS, $profile->status());
        self::assertSame('523456788', $profile->siren()?->value());
        self::assertSame('FR31523456788', $profile->vatNumber()?->value());
    }

    public function testTheVatNumberIsOptional(): void
    {
        self::assertNull(ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('french_business', 'Centre équestre du Lac', '812345676', '  '))->vatNumber());
    }

    public function testAFrenchBusinessNeedsASiren(): void
    {
        $this->expectException(InvalidClientTaxProfileException::class);
        $this->expectExceptionMessage('siren_required');

        ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('french_business', 'Calanques', ' '));
    }

    public function testAnUnknownStatusIsATaxProfileError(): void
    {
        $this->expectException(InvalidClientTaxProfileException::class);
        $this->expectExceptionMessage('status_unknown');

        ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('b2b', 'Calanques', '523456788'));
    }

    public function testAnInvalidSirenSurfaces(): void
    {
        $this->expectException(InvalidSirenException::class);

        ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('french_business', 'Calanques', '523456789'));
    }

    public function testAnInvalidVatNumberSurfaces(): void
    {
        $this->expectException(InvalidFrenchVatNumberException::class);

        ClientTaxProfileFactory::fromDto(new ClientTaxProfileDto('french_business', 'Calanques', '523456788', 'FR23523456788'));
    }
}
