<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Domain;

use App\Context\ClinicListing\Domain\Exception\InvalidListingCoordinatesException;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\ListingProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingValueObjectsTest extends TestCase
{
    public function testAnAddressIsCleanedAndNeedsAStreetAndACity(): void
    {
        $address = ListingAddress::fromParts("  18 rue\tdes Abbesses ", ' ', 'Paris ');
        self::assertNotNull($address);
        self::assertSame(['18 rue des Abbesses', null, 'Paris'], [$address->line(), $address->postalCode(), $address->city()]);
        self::assertSame('18 rue des Abbesses Paris', $address->toQuery());

        $full = ListingAddress::fromParts('18 rue des Abbesses', '75018', 'Paris');
        self::assertNotNull($full);
        self::assertSame('18 rue des Abbesses 75018 Paris', $full->toQuery());
        self::assertFalse($full->equals($address));
        self::assertTrue($full->equals(ListingAddress::fromParts('18 rue des Abbesses', '75018', 'Paris') ?? $address));

        self::assertNull(ListingAddress::fromParts(null, '75018', 'Paris'));
        self::assertNull(ListingAddress::fromParts('18 rue des Abbesses', '75018', ' '));
    }

    public function testTheHashIgnoresCaseAndSpacingOnly(): void
    {
        $hash = ListingAddress::fromParts('18 rue des Abbesses', '75018', 'Paris')?->hash();

        self::assertSame(64, \strlen((string) $hash));
        self::assertSame($hash, ListingAddress::fromParts('18  RUE DES ABBESSES', '75018', 'paris')?->hash());
        self::assertNotSame($hash, ListingAddress::fromParts('18 rue des Abbesses', '75017', 'Paris')?->hash());
    }

    public function testCoordinatesAreRoundedToSixDecimals(): void
    {
        $here = ListingCoordinates::fromDegrees(48.8846123456, -2.3381234567);

        self::assertSame([48.884612, -2.338123], [$here->latitude(), $here->longitude()]);
        self::assertTrue($here->equals(ListingCoordinates::fromDegrees(48.884612, -2.338123)));
        self::assertFalse($here->equals(ListingCoordinates::fromDegrees(48.884612, 2.338123)));
        self::assertSame([90.0, 180.0], [ListingCoordinates::fromDegrees(90.0, 180.0)->latitude(), ListingCoordinates::fromDegrees(90.0, 180.0)->longitude()]);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function provideCoordinatesOutOfRangeAreRefusedCases(): iterable
    {
        yield 'latitude below' => [-90.1, 0.0];
        yield 'latitude above' => [90.1, 0.0];
        yield 'longitude below' => [0.0, -180.1];
        yield 'longitude above' => [0.0, 180.1];
    }

    #[DataProvider('provideCoordinatesOutOfRangeAreRefusedCases')]
    public function testCoordinatesOutOfRangeAreRefused(float $latitude, float $longitude): void
    {
        $this->expectException(InvalidListingCoordinatesException::class);

        ListingCoordinates::fromDegrees($latitude, $longitude);
    }

    public function testAProfileKeepsWhatClinicSaid(): void
    {
        $profile = new ListingProfile('Clinique du Parc', null, null, 'Indian/Reunion', [], false);

        self::assertSame(['Clinique du Parc', null, null, 'Indian/Reunion', [], false], [
            $profile->name(), $profile->address(), $profile->phone(), $profile->timezone(), $profile->openingHours(), $profile->isActive(),
        ]);
    }
}
