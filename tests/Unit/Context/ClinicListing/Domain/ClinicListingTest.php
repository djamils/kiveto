<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Domain;

use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Tests\Unit\Context\ClinicListing\Support\ClinicListingFakes;
use PHPUnit\Framework\TestCase;

final class ClinicListingTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-0000000a1001';
    private const string CLINIC = '01960000-0000-7000-8000-0000000a1c01';

    public function testAFirstProjectionCarriesTheProfileAndTheHashOfItsAddress(): void
    {
        $profile = ClinicListingFakes::profile();
        $now     = new \DateTimeImmutable('2026-11-02 09:00:00');
        $listing = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, $profile, $now);

        self::assertSame(self::ID, $listing->id()->toString());
        self::assertSame(self::CLINIC, $listing->clinicId());
        self::assertSame($profile, $listing->profile());
        self::assertNull($listing->coordinates());
        self::assertSame($profile->address()?->hash(), $listing->addressHash());
        self::assertSame($now, $listing->refreshedAt());
        self::assertTrue($listing->isActive());

        $withoutAddress = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, ClinicListingFakes::profile(line: null), $now);
        self::assertNull($withoutAddress->addressHash());
    }

    public function testARefreshAsksForGeocodingOnlyWhenTheNormalisedAddressChanges(): void
    {
        $listing = $this->geocodedListing();
        $later   = new \DateTimeImmutable('2026-11-03 10:00:00');

        // Renamed, same address written differently: nothing to geocode, coordinates kept
        self::assertFalse($listing->refresh(ClinicListingFakes::profile(name: 'Clinique du Lac', line: '18  RUE des abbesses', active: false), $later));
        self::assertSame('Clinique du Lac', $listing->profile()->name());
        self::assertFalse($listing->isActive());
        self::assertSame($later, $listing->refreshedAt());
        self::assertNotNull($listing->coordinates());

        // Moved: geocoding asked, the previous coordinates kept until the new ones come
        $before = $listing->addressHash();
        self::assertTrue($listing->refresh(ClinicListingFakes::profile(line: '7 quai Saint-Antoine', city: 'Lyon', postalCode: '69002'), $later));
        self::assertNotSame($before, $listing->addressHash());
        self::assertNotNull($listing->coordinates());

        // No address any more: the coordinates go at once, nothing to geocode
        self::assertFalse($listing->refresh(ClinicListingFakes::profile(line: null), $later));
        self::assertNull($listing->addressHash());
        self::assertNull($listing->coordinates());
    }

    public function testCoordinatesAreAttachedOnlyForTheCurrentAddress(): void
    {
        $listing = ClinicListing::project(ClinicListingId::fromString(self::ID), self::CLINIC, ClinicListingFakes::profile(), new \DateTimeImmutable('2026-11-02'));
        $hash    = (string) $listing->addressHash();
        $here    = ListingCoordinates::fromDegrees(48.884, 2.338);

        self::assertFalse($listing->attachCoordinates('stale', $here));
        self::assertNull($listing->coordinates());

        self::assertTrue($listing->attachCoordinates($hash, $here));
        self::assertSame($here, $listing->coordinates());

        // The geocoder could not place the address
        self::assertTrue($listing->attachCoordinates($hash, null));
        self::assertNull($listing->coordinates());
    }

    private function geocodedListing(): ClinicListing
    {
        $profile = ClinicListingFakes::profile();

        return ClinicListing::reconstitute(
            ClinicListingId::fromString(self::ID),
            self::CLINIC,
            $profile,
            ListingCoordinates::fromDegrees(48.884, 2.338),
            $profile->address()?->hash(),
            new \DateTimeImmutable('2026-11-02'),
        );
    }
}
