<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidLetterheadException;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Letterhead::class)]
#[CoversClass(InvalidLetterheadException::class)]
final class LetterheadTest extends TestCase
{
    public function testAFullLetterheadIsNormalised(): void
    {
        $letterhead = Letterhead::create(
            legalName: '  Clinique Vétérinaire de Paris ',
            legalForm: ' SELARL ',
            shareCapital: '30 000 €',
            siret: '812 456 903 00028',
            vatNumber: 'fr 20 812456903',
            rcsCity: 'Paris',
            rcsNumber: '812 456 903',
            apeCode: '7500z',
            ordinalNumber: '75-1284',
            address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR', postalCode: '75018'),
            phone: '+33 1 42 55 18 90',
            email: '  Contact@Vet-Paris.FR ',
            website: 'www.vet-paris.fr',
        );

        self::assertSame('Clinique Vétérinaire de Paris', $letterhead->legalName);
        self::assertSame('SELARL', $letterhead->legalForm);
        // Whatever the SIRET is typed with, it is stored as its 14 digits.
        self::assertSame('81245690300028', $letterhead->siret);
        self::assertSame('FR20812456903', $letterhead->vatNumber);
        self::assertSame('812456903', $letterhead->siren()?->value());
        self::assertSame('FR20812456903', $letterhead->vatNumber()?->value());
        self::assertSame('7500Z', $letterhead->apeCode);
        self::assertSame('+33142551890', $letterhead->phone?->toString());
        self::assertSame('contact@vet-paris.fr', $letterhead->email?->toString());
        self::assertSame('Paris', $letterhead->address?->city);
        self::assertFalse($letterhead->isEmpty());
    }

    public function testBlankFieldsAreStoredAsNothing(): void
    {
        $letterhead = Letterhead::create(
            legalName: '   ',
            legalForm: '',
            siret: '  ',
            vatNumber: '',
            apeCode: '   ',
            phone: '  ',
            email: '',
            website: '   ',
        );

        self::assertTrue($letterhead->isEmpty());
        self::assertNull($letterhead->legalName);
        self::assertNull($letterhead->siret);
        self::assertNull($letterhead->vatNumber);
        self::assertNull($letterhead->apeCode);
        self::assertNull($letterhead->phone);
        self::assertNull($letterhead->email);
    }

    public function testASiretMadeOfSeparatorsOnlyIsNoSiret(): void
    {
        self::assertNull(Letterhead::create(siret: '-- --')->siret);
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: string, 3: string}>
     */
    public static function provideAnInvalidFiscalIdentityIsRefusedCases(): iterable
    {
        yield 'a SIRET of the wrong length' => ['812 456 903', null, InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_FORMAT];
        yield 'a SIRET whose SIREN fails its check digit' => ['81245690400028', null, InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_CHECKSUM];
        yield 'a SIRET that fails its own check digit' => ['81245690300027', null, InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_CHECKSUM];
        yield 'a La Poste SIRET whose digits do not add up to a multiple of 5' => ['35600000000002', null, InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_CHECKSUM];
        yield 'a VAT number without a SIRET' => [null, 'FR20812456903', InvalidLetterheadException::FIELD_SIRET, InvalidLetterheadException::REASON_MISSING];
        yield 'a VAT number of the wrong shape' => ['81245690300028', 'FR2081245690', InvalidLetterheadException::FIELD_VAT_NUMBER, InvalidLetterheadException::REASON_FORMAT];
        yield 'a VAT number of another company' => ['81245690300028', 'FR00901122333', InvalidLetterheadException::FIELD_VAT_NUMBER, InvalidLetterheadException::REASON_SIREN_MISMATCH];
        yield 'a VAT number with a wrong key' => ['81245690300028', 'FR41812456903', InvalidLetterheadException::FIELD_VAT_NUMBER, InvalidLetterheadException::REASON_KEY];
    }

    #[DataProvider('provideAnInvalidFiscalIdentityIsRefusedCases')]
    public function testAnInvalidFiscalIdentityIsRefused(?string $siret, ?string $vatNumber, string $field, string $reason): void
    {
        try {
            Letterhead::create(siret: $siret, vatNumber: $vatNumber);
            self::fail('The letterhead should have been refused.');
        } catch (InvalidLetterheadException $exception) {
            self::assertSame($field, $exception->field);
            self::assertSame($reason, $exception->reason);
        }
    }

    public function testTheMessageNamesTheFieldTheInputAndTheReason(): void
    {
        $this->expectException(InvalidLetterheadException::class);
        $this->expectExceptionMessage('Invalid letterhead siret "8124569030" (format).');

        Letterhead::create(siret: '812 456 9030');
    }

    public function testALaPosteSiretIsCheckedTheWayInseeChecksIt(): void
    {
        // Fails the Luhn check, yet its digits add up to 15: a valid La Poste SIRET.
        $letterhead = Letterhead::create(siret: '356 000 000 00001');

        self::assertSame('356000000', $letterhead->siren()?->value());
    }

    public function testTheTwoEstablishmentsOfOneCompanyShareItsSirenAndVatNumber(): void
    {
        $lyon   = Letterhead::create(siret: '90112233300015', vatNumber: 'FR00901122333');
        $cassis = Letterhead::create(siret: '90112233300023', vatNumber: 'FR00901122333');

        self::assertNotNull($lyon->siren());
        self::assertNotNull($cassis->siren());
        self::assertTrue($lyon->siren()->equals($cassis->siren()));
        self::assertSame('FR00901122333', $cassis->vatNumber()?->value());
    }

    public function testAStoredLetterheadLoadsEvenWhenItsFiscalIdentityDoesNotHold(): void
    {
        $letterhead = Letterhead::reconstitute(
            legalName: ' Clinique ',
            siret: '812 456 903 00027',
            vatNumber: 'fr41812456903',
            ordinalCouncil: 'nowhere',
        );

        self::assertSame('Clinique', $letterhead->legalName);
        self::assertSame('81245690300027', $letterhead->siret);
        self::assertSame('FR41812456903', $letterhead->vatNumber);
        self::assertNull($letterhead->ordinalCouncil);
        self::assertNull($letterhead->siren());
        self::assertNull($letterhead->vatNumber());
    }

    public function testAStoredVatNumberThatDoesNotMatchAValidSirenIsNoVatNumber(): void
    {
        self::assertNull(Letterhead::reconstitute(siret: '81245690300028', vatNumber: 'FR41812456903')->vatNumber());
        self::assertNull(Letterhead::reconstitute(siret: '81245690300028')->vatNumber());
        self::assertNull(Letterhead::reconstitute(vatNumber: 'FR20812456903')->vatNumber());
        self::assertNull(Letterhead::empty()->siren());
    }

    public function testAnInvalidEmailIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Letterhead::create(email: 'not-an-email');
    }

    public function testAnInvalidPhoneIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Letterhead::create(phone: '01 42 55 18 90');
    }

    public function testAnEmptyLetterheadIsEmpty(): void
    {
        $empty = Letterhead::empty();

        self::assertTrue($empty->isEmpty());
        self::assertTrue($empty->equals(Letterhead::create()));
    }

    public function testTwoLetterheadsWithTheSameContentAreEqual(): void
    {
        $address = PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR', postalCode: '75018');

        $first = Letterhead::create(
            legalName: 'Clinique',
            siret: '81245690300028',
            address: $address,
            phone: '+33142551890',
            email: 'contact@vet-paris.fr',
        );

        $second = Letterhead::create(
            legalName: 'Clinique',
            siret: '812 456 903 00028',
            address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR', postalCode: '75018'),
            phone: '+33 1 42 55 18 90',
            email: 'CONTACT@vet-paris.fr',
        );

        self::assertTrue($first->equals($second));
        self::assertTrue($second->equals($first));
    }

    public function testAnAddressOnOneSideOnlyBreaksEquality(): void
    {
        $withAddress = Letterhead::create(
            legalName: 'Clinique',
            address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR'),
        );
        $without = Letterhead::create(legalName: 'Clinique');

        self::assertFalse($withAddress->equals($without));
        self::assertFalse($without->equals($withAddress));
    }

    public function testADifferentAddressBreaksEquality(): void
    {
        $paris = Letterhead::create(address: PostalAddress::create('18 rue des Abbesses', 'Paris', 'FR'));
        $lyon  = Letterhead::create(address: PostalAddress::create('7 quai Saint-Antoine', 'Lyon', 'FR'));

        self::assertFalse($paris->equals($lyon));
    }

    public function testADifferentFieldBreaksEquality(): void
    {
        self::assertFalse(
            Letterhead::create(rcsCity: 'Paris', rcsNumber: '1', ordinalNumber: '75-1', website: 'a', legalForm: 'SELARL', shareCapital: '1 €')
                ->equals(Letterhead::create(rcsCity: 'Lyon', rcsNumber: '1', ordinalNumber: '75-1', website: 'a', legalForm: 'SELARL', shareCapital: '1 €')),
        );
        self::assertFalse(Letterhead::reconstitute(vatNumber: 'FR1')->equals(Letterhead::reconstitute(vatNumber: 'FR2')));
        self::assertFalse(Letterhead::create(apeCode: '7500Z')->equals(Letterhead::create(apeCode: '8500Z')));
        self::assertFalse(Letterhead::create(phone: '+33142551890')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(email: 'a@b.fr')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(siret: '81245690300028')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(rcsNumber: '1')->equals(Letterhead::create()));
    }
}
