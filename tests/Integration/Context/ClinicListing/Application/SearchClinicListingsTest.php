<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicListing\Application;

use App\Context\ClinicListing\Application\Query\GetClinicListing\ClinicListingDetail;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingPage;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingSummary;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListings;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSearchException;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * AC 77, AC 82, AC 270 through the query bus and the SQL read model: only
 * listed active clinics, `q` over name and city with `%` / `_` taken
 * literally, the bounding box and Haversine order, clinics without
 * coordinates absent around a point only, keyset cursors bound to their
 * point, and `onlineBooking` from Scheduling.
 */
final class SearchClinicListingsTest extends KernelTestCase
{
    // Lyon centre: Bellecour
    private const float LAT = 45.757814;
    private const float LNG = 4.832011;

    private const array CLINICS = [
        // key => [listing id, clinic id, name, city, lat, lng, listed, active, accepts new clients]
        'lilas'        => ['01960000-0000-7000-8000-0000000b2001', '01960000-0000-7000-8000-0000000b2c01', 'Clinique des Lilas', 'Lyon', 45.760000, 4.835000, true, true, true],
        'villeurbanne' => ['01960000-0000-7000-8000-0000000b2002', '01960000-0000-7000-8000-0000000b2c02', 'Clinique du Tonkin', 'Villeurbanne', 45.771000, 4.880000, true, true, false],
        'lyonnais'     => ['01960000-0000-7000-8000-0000000b2003', '01960000-0000-7000-8000-0000000b2c03', 'Cabinet Lyonnais', 'Caluire', null, null, true, true, true],
        'unlisted'     => ['01960000-0000-7000-8000-0000000b2004', '01960000-0000-7000-8000-0000000b2c04', 'Clinique Lyon Sud', 'Lyon', 45.700000, 4.820000, false, true, true],
        'suspended'    => ['01960000-0000-7000-8000-0000000b2005', '01960000-0000-7000-8000-0000000b2c05', 'Clinique Lyon Nord', 'Lyon', 45.790000, 4.830000, true, false, true],
        'percent'      => ['01960000-0000-7000-8000-0000000b2006', '01960000-0000-7000-8000-0000000b2c06', 'Clinique 100% Chats', 'Paris', 48.856000, 2.352000, true, true, true],
        'thousand'     => ['01960000-0000-7000-8000-0000000b2007', '01960000-0000-7000-8000-0000000b2c07', 'Clinique 1000 Chats', 'Paris', 48.860000, 2.350000, true, true, true],
        'underscore'   => ['01960000-0000-7000-8000-0000000b2008', '01960000-0000-7000-8000-0000000b2c08', 'Clinique A_B', 'Marseille', 43.296000, 5.369000, true, true, true],
        'ab'           => ['01960000-0000-7000-8000-0000000b2009', '01960000-0000-7000-8000-0000000b2c09', 'Clinique AXB', 'Marseille', 43.297000, 5.370000, true, true, true],
    ];

    protected function setUp(): void
    {
        $listings = self::service(ClinicListingRepositoryInterface::class);
        $settings = self::service(ListingSettingsRepositoryInterface::class);

        foreach (self::CLINICS as [$id, $clinicId, $name, $city, $lat, $lng, $listed, $active, $accepts]) {
            $profile = new ListingProfile($name, ListingAddress::fromParts('1 place Bellecour', '69002', $city), '+33478000000', 'Europe/Paris', [['day' => 1, 'opensAt' => '09:00', 'closesAt' => '18:00']], $active);
            $listings->save(ClinicListing::reconstitute(
                ClinicListingId::fromString($id),
                $clinicId,
                $profile,
                null === $lat || null === $lng ? null : ListingCoordinates::fromDegrees($lat, $lng),
                $profile->address()?->hash(),
                new \DateTimeImmutable('2026-11-02 09:00:00'),
            ));

            $clinicSettings = ListingSettings::defaults($clinicId);
            $clinicSettings->update($listed, $accepts, ['Chats', 'NAC']);
            $settings->save($clinicSettings);
        }

        // Scheduling takes new clients at Lilas, Tonkin and Lyonnais (Tonkin does not accept them)
        $online = self::service(OnlineBookingSettingsRepositoryInterface::class);
        $motifs = self::service(BookingMotifRepositoryInterface::class);
        foreach (['lilas' => 'e1', 'villeurbanne' => 'e2', 'lyonnais' => 'e3'] as $key => $suffix) {
            $clinicId = ClinicId::fromString(self::CLINICS[$key][1]);
            $online->save(new OnlineBookingSettings($clinicId, true, 15, 120, 24, 60, 24, true));
            $motifs->save(BookingMotif::create(BookingMotifId::fromString('01960000-0000-7000-8000-0000000b2' . $suffix . '0'), $clinicId, 'Consultation', null, 30, true, ConfirmationMode::AUTO, [PlanningBlockType::CONSULTATION], [UserId::fromString('01960000-0000-7000-8000-0000000b2fff')], null, true, 0));
        }
    }

    public function testByNameOrCityOnlyListedActiveClinicsWithTheirOnlineBooking(): void
    {
        $page = $this->search(new SearchClinicListings(q: 'lyon'));

        // « Cabinet Lyonnais » by name, Lilas by city; neither the unlisted nor the suspended one
        self::assertSame(['Cabinet Lyonnais', 'Clinique des Lilas'], self::names($page));
        self::assertNull($page->nextCursor);

        [$lyonnais, $lilas] = $page->items;
        self::assertSame([self::CLINICS['lilas'][0], true, true, ['Chats', 'NAC'], '+33478000000', null, null], [$lilas->id, $lilas->onKiveto, $lilas->onlineBooking, $lilas->tags, $lilas->phone, $lilas->distanceM, $lilas->nextAvailableAt]);
        self::assertSame(['1 place Bellecour', '69002', 'Lyon'], [$lilas->address?->line, $lilas->address?->postalCode, $lilas->address?->city]);
        self::assertTrue($lyonnais->onlineBooking);

        // Tonkin does not accept new clients: no online booking, whatever Scheduling says
        $tonkin = $this->search(new SearchClinicListings(q: 'tonkin'))->items[0];
        self::assertFalse($tonkin->onlineBooking);
        // Paris clinics have no Scheduling settings
        self::assertFalse($this->search(new SearchClinicListings(q: '1000'))->items[0]->onlineBooking);
    }

    public function testPercentAndUnderscoreAreTakenLiterally(): void
    {
        self::assertSame(['Clinique 100% Chats'], self::names($this->search(new SearchClinicListings(q: '100%'))));
        self::assertSame(['Clinique A_B'], self::names($this->search(new SearchClinicListings(q: 'a_b'))));
        self::assertSame(['Clinique A_B', 'Clinique AXB'], self::names($this->search(new SearchClinicListings(q: 'marseille'))));
    }

    public function testWithoutQueryEveryListedActiveClinicComesByNamePageAfterPage(): void
    {
        $first = $this->search(new SearchClinicListings(limit: 4));
        self::assertSame(['Cabinet Lyonnais', 'Clinique 100% Chats', 'Clinique 1000 Chats', 'Clinique A_B'], self::names($first));
        self::assertNotNull($first->nextCursor);

        $second = $this->search(new SearchClinicListings(cursor: $first->nextCursor, limit: 4));
        self::assertSame(['Clinique AXB', 'Clinique des Lilas', 'Clinique du Tonkin'], self::names($second));
        self::assertNull($second->nextCursor);
    }

    public function testAroundAPointNearestFirstWithinTheRadiusAndWithoutTheUnplacedClinics(): void
    {
        $first = $this->search(new SearchClinicListings(lat: self::LAT, lng: self::LNG, limit: 1));

        // Lilas (~350 m), then Tonkin (~4 km); Cabinet Lyonnais has no coordinates, Paris and Marseille are too far
        self::assertSame(['Clinique des Lilas'], self::names($first));
        self::assertGreaterThan(200, $first->items[0]->distanceM);
        self::assertLessThan(500, $first->items[0]->distanceM);
        self::assertNotNull($first->nextCursor);

        $second = $this->search(new SearchClinicListings(lat: self::LAT, lng: self::LNG, cursor: $first->nextCursor, limit: 1));
        self::assertSame(['Clinique du Tonkin'], self::names($second));
        self::assertGreaterThan(3_000, $second->items[0]->distanceM);
        self::assertLessThan(5_000, $second->items[0]->distanceM);
        self::assertNull($second->nextCursor);

        // With a text, around the point too
        self::assertSame(['Clinique du Tonkin'], self::names($this->search(new SearchClinicListings(q: 'villeur', lat: self::LAT, lng: self::LNG))));

        // Around Paris: both Paris clinics, the nearest first
        self::assertSame(['Clinique 100% Chats', 'Clinique 1000 Chats'], self::names($this->search(new SearchClinicListings(lat: 48.8566, lng: 2.3522))));
    }

    public function testACursorOfAnotherPointOrAnOutOfRangeLatitudeIsRefused(): void
    {
        $first = $this->search(new SearchClinicListings(lat: self::LAT, lng: self::LNG, limit: 1));
        \assert(null !== $first->nextCursor);

        try {
            $this->search(new SearchClinicListings(lat: 48.8566, lng: 2.3522, cursor: $first->nextCursor));
            self::fail('The cursor should be refused.');
        } catch (InvalidCursorException) {
        }

        try {
            $this->search(new SearchClinicListings(lat: 95.0, lng: self::LNG));
            self::fail('The latitude should be refused.');
        } catch (InvalidListingSearchException $e) {
            self::assertSame('lat', $e->field);
        }
    }

    public function testAListingIsReadOnlyWhenListedAndActive(): void
    {
        $detail = self::service(QueryBusInterface::class)->ask(new GetClinicListing(self::CLINICS['lilas'][0]));
        self::assertInstanceOf(ClinicListingDetail::class, $detail);
        self::assertSame([self::CLINICS['lilas'][1], 'Clinique des Lilas', 'Europe/Paris', true, true, ['Chats', 'NAC']], [$detail->clinicId, $detail->name, $detail->timezone, $detail->acceptsNewClients, $detail->onlineBooking, $detail->tags]);
        self::assertSame([1, '09:00', '18:00'], [$detail->openingHours[0]->day, $detail->openingHours[0]->opensAt, $detail->openingHours[0]->closesAt]);

        foreach ([self::CLINICS['unlisted'][0], self::CLINICS['suspended'][0], self::CLINICS['lilas'][1], 'not-a-listing'] as $id) {
            self::assertNull(self::service(QueryBusInterface::class)->ask(new GetClinicListing($id)));
        }
    }

    private function search(SearchClinicListings $query): ClinicListingPage
    {
        $page = self::service(QueryBusInterface::class)->ask($query);
        \assert($page instanceof ClinicListingPage);

        return $page;
    }

    /**
     * @return list<string>
     */
    private static function names(ClinicListingPage $page): array
    {
        return array_map(static fn (ClinicListingSummary $item): string => $item->name, $page->items);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
