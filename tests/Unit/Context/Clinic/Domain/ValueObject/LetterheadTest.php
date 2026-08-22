<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Letterhead::class)]
final class LetterheadTest extends TestCase
{
    public function testAFullLetterheadIsNormalised(): void
    {
        $letterhead = Letterhead::create(
            legalName: '  Clinique Vétérinaire de Paris ',
            legalForm: ' SELARL ',
            shareCapital: '30 000 €',
            siret: '812 456 903 00027',
            vatNumber: 'fr41812456903',
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
        self::assertSame('81245690300027', $letterhead->siret);
        self::assertSame('FR41812456903', $letterhead->vatNumber);
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

    public function testASiretOfTheWrongLengthIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A SIRET is made of 14 digits, got: "812 456 903".');

        Letterhead::create(siret: '812 456 903');
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
            siret: '81245690300027',
            address: $address,
            phone: '+33142551890',
            email: 'contact@vet-paris.fr',
        );

        $second = Letterhead::create(
            legalName: 'Clinique',
            siret: '812 456 903 00027',
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
        self::assertFalse(Letterhead::create(vatNumber: 'FR1')->equals(Letterhead::create(vatNumber: 'FR2')));
        self::assertFalse(Letterhead::create(apeCode: '7500Z')->equals(Letterhead::create(apeCode: '8500Z')));
        self::assertFalse(Letterhead::create(phone: '+33142551890')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(email: 'a@b.fr')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(siret: '81245690300027')->equals(Letterhead::create()));
        self::assertFalse(Letterhead::create(rcsNumber: '1')->equals(Letterhead::create()));
    }
}
