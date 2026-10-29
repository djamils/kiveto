<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LetterheadDto::class)]
final class LetterheadDtoTest extends TestCase
{
    public function testTheAddressIsFoldedIntoThePrintedLines(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(
            address: PostalAddress::create(
                streetLine1: '7 quai Saint-Antoine',
                city: 'Lyon',
                countryCode: 'FR',
                streetLine2: 'Bâtiment B',
                postalCode: '69002',
            ),
        ));

        self::assertSame(['7 quai Saint-Antoine', 'Bâtiment B', '69002 Lyon'], $dto->addressLines);
        self::assertSame('Lyon', $dto->city);
    }

    public function testAnAddressWithoutAPostalCodeStillPrintsItsCity(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(
            address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR'),
        ));

        self::assertSame(['18 rue des Abbesses', 'Paris'], $dto->addressLines);
    }

    public function testALetterheadWithoutAnAddressPrintsNoAddressLine(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(legalName: 'Clinique'));

        self::assertSame([], $dto->addressLines);
        self::assertNull($dto->city);
        self::assertNull($dto->phone);
        self::assertNull($dto->email);
    }

    public function testTheSiretIsGroupedTheWayItIsWrittenOnPaper(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(siret: '81245690300028'));

        self::assertSame('812 456 903 00028', $dto->formattedSiret());
    }

    public function testNoSiretMeansNothingToPrint(): void
    {
        self::assertNull(LetterheadDto::fromDomain(Letterhead::empty())->formattedSiret());
        self::assertNull(LetterheadDto::fromDomain(Letterhead::empty())->legalSentence());
    }

    public function testTheLegalSentenceGathersWhateverIsFilledIn(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(
            legalForm: 'SELARL',
            shareCapital: '30 000 €',
            rcsCity: 'Paris',
            rcsNumber: '812 456 903',
            apeCode: '7500Z',
        ));

        self::assertSame(
            'SELARL, au capital de 30 000 €, RCS Paris 812 456 903, code APE 7500Z',
            $dto->legalSentence(),
        );
    }

    public function testAnRcsNumberWithoutACityIsPrintedAlone(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(rcsNumber: '812 456 903'));

        self::assertSame('RCS 812 456 903', $dto->legalSentence());
    }

    public function testTheContactDetailsAreFlattenedToStrings(): void
    {
        $dto = LetterheadDto::fromDomain(Letterhead::create(
            phone: '+33142551890',
            email: 'contact@vet-paris.fr',
            website: 'www.vet-paris.fr',
        ));

        self::assertSame('+33142551890', $dto->phone);
        self::assertSame('contact@vet-paris.fr', $dto->email);
        self::assertSame('www.vet-paris.fr', $dto->website);
    }

    public function testTheDocumentLetterheadCarriesThePrintedValues(): void
    {
        $document = LetterheadDto::fromDomain(Letterhead::create(
            legalName: 'Clinique Vétérinaire de Paris',
            legalForm: 'SELARL',
            siret: '81245690300028',
            vatNumber: 'FR20812456903',
            ordinalNumber: '21284',
            address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR', postalCode: '75018'),
            website: 'www.vet-paris.fr',
            liabilityInsurer: 'MACSF',
        ))->toDocumentLetterhead();

        self::assertSame('Clinique Vétérinaire de Paris', $document->legalName);
        self::assertSame(['18 rue des Abbesses', '75018 Paris'], $document->addressLines);
        self::assertSame('Paris', $document->city);
        self::assertSame('www.vet-paris.fr', $document->website);
        self::assertSame('812 456 903 00028', $document->formattedSiret);
        self::assertSame('FR20812456903', $document->vatNumber);
        self::assertSame('21284', $document->ordinalNumber);
        self::assertSame('SELARL', $document->legalSentence);
        self::assertSame('Assurance responsabilité civile professionnelle : MACSF', $document->liabilitySentence);
        self::assertNull($document->phone);
        self::assertNull($document->email);
        self::assertNull($document->ordinalCouncil);
    }
}
