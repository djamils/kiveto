<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Application\Query;

use App\Context\ClinicListing\Application\Query\SearchClinicListings\ListingCursor;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListings;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListingsHandler;
use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSearchException;
use App\Shared\Application\Pagination\Cursor;
use App\Tests\Unit\Context\ClinicListing\Support\ClinicListingFakes;
use App\Tests\Unit\Context\ClinicListing\Support\FakeListingReadModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AC 77, AC 82, AC 270 (query side): the search rules, the keyset cursors
 * bound to their ordering and point, and `onlineBooking` asked to Scheduling
 * once per page.
 */
final class SearchClinicListingsHandlerTest extends TestCase
{
    private const string L1 = '01960000-0000-7000-8000-0000000a3001';
    private const string L2 = '01960000-0000-7000-8000-0000000a3002';
    private const string L3 = '01960000-0000-7000-8000-0000000a3003';
    private const string C1 = '01960000-0000-7000-8000-0000000a3c01';
    private const string C2 = '01960000-0000-7000-8000-0000000a3c02';
    private const string C3 = '01960000-0000-7000-8000-0000000a3c03';

    private FakeListingReadModel $listings;
    private ClinicListingFakes $scheduling;
    private SearchClinicListingsHandler $handler;

    protected function setUp(): void
    {
        $this->listings   = new FakeListingReadModel();
        $this->scheduling = new ClinicListingFakes();
        $this->handler    = new SearchClinicListingsHandler($this->listings, $this->scheduling);
    }

    public function testByNameWithANextCursorAndOnlineBookingAskedOncePerPage(): void
    {
        $this->listings->rows = [
            FakeListingReadModel::row(self::L1, self::C1, 'Clinique A'),
            FakeListingReadModel::row(self::L2, self::C2, 'Clinique B', acceptsNewClients: false, city: null),
            FakeListingReadModel::row(self::L3, self::C3, 'Clinique C'),
        ];
        $this->scheduling->openToNewClients = [self::C1, self::C2];

        $page = ($this->handler)(new SearchClinicListings(q: '  lyon ', limit: 2));

        self::assertSame(['by' => 'name', 'q' => 'lyon', 'afterName' => null, 'afterId' => null, 'limit' => 3], $this->listings->calls[0]);
        self::assertCount(2, $page->items);
        [$a, $b] = $page->items;
        self::assertSame([self::L1, 'Clinique A', '+33142551890', null, true, ['NAC'], true, null], [$a->id, $a->name, $a->phone, $a->distanceM, $a->onlineBooking, $a->tags, $a->onKiveto, $a->nextAvailableAt]);
        self::assertSame(['1 rue de la Paix', '75002', 'Paris'], [$a->address?->line, $a->address?->postalCode, $a->address?->city]);
        // B does not accept new clients: never online, never asked
        self::assertFalse($b->onlineBooking);
        self::assertNull($b->address);
        self::assertSame([[self::C1]], $this->scheduling->askedOpen);

        self::assertNotNull($page->nextCursor);
        $cursor = ListingCursor::decode($page->nextCursor, null, null);
        self::assertSame([self::L2, 'Clinique B', null], [$cursor->id, $cursor->name, $cursor->distanceM]);

        ($this->handler)(new SearchClinicListings(cursor: $page->nextCursor, limit: 2));
        self::assertSame(['by' => 'name', 'q' => null, 'afterName' => 'Clinique B', 'afterId' => self::L2, 'limit' => 3], $this->listings->calls[1]);
    }

    public function testTheLastPageHasNoCursorAndAPageWithoutNewClientsAsksNothing(): void
    {
        $this->listings->rows = [FakeListingReadModel::row(self::L1, self::C1, 'Clinique A', acceptsNewClients: false)];

        $page = ($this->handler)(new SearchClinicListings(q: ' '));

        self::assertNull($page->nextCursor);
        self::assertSame(Cursor::DEFAULT_LIMIT + 1, $this->listings->calls[0]['limit']);
        self::assertNull($this->listings->calls[0]['q']);
        self::assertSame([], $this->scheduling->askedOpen);
    }

    public function testAroundAPointTheDistanceCursorCarriesThePoint(): void
    {
        $this->listings->rows = [
            FakeListingReadModel::row(self::L1, self::C1, 'Clinique A', distanceM: 120),
            FakeListingReadModel::row(self::L2, self::C2, 'Clinique B', distanceM: 4_500),
        ];

        $page = ($this->handler)(new SearchClinicListings(q: 'pa', lat: 48.85, lng: 2.35, limit: 1));

        self::assertSame(['by' => 'distance', 'q' => 'pa', 'lat' => 48.85, 'lng' => 2.35, 'radius' => SearchClinicListings::RADIUS_METRES, 'afterDistance' => null, 'afterId' => null, 'limit' => 2], $this->listings->calls[0]);
        self::assertSame(120, $page->items[0]->distanceM);
        self::assertNotNull($page->nextCursor);

        ($this->handler)(new SearchClinicListings(lat: 48.85, lng: 2.35, cursor: $page->nextCursor, limit: 1));
        self::assertSame([120, self::L1], [$this->listings->calls[1]['afterDistance'], $this->listings->calls[1]['afterId']]);

        // Issued for that point: another point, or no point at all, refuses it
        foreach ([[48.86, 2.35], [48.85, 2.36], [null, null]] as [$lat, $lng]) {
            try {
                ($this->handler)(new SearchClinicListings(lat: $lat, lng: $lng, cursor: $page->nextCursor));
                self::fail('The cursor should be refused.');
            } catch (InvalidCursorException $e) {
                self::assertSame('The cursor is invalid for this search.', $e->getMessage());
            }
        }
    }

    public function testThePageSizeIsClampedLikeEveryCursorRoute(): void
    {
        ($this->handler)(new SearchClinicListings(limit: 51));
        ($this->handler)(new SearchClinicListings(limit: 0));

        self::assertSame([Cursor::MAX_LIMIT + 1, 2], array_column($this->listings->calls, 'limit'));
    }

    /**
     * @return iterable<string, array{SearchClinicListings, string, string}>
     */
    public static function provideASearchOutOfTheRulesIsRefusedCases(): iterable
    {
        yield 'one character' => [new SearchClinicListings(q: ' L '), 'q', 'Saisissez au moins 2 caractères.'];
        yield 'longitude alone' => [new SearchClinicListings(lng: 2.35), 'lat', 'Indiquez la latitude avec la longitude.'];
        yield 'latitude alone' => [new SearchClinicListings(lat: 48.85), 'lng', 'Indiquez la longitude avec la latitude.'];
        yield 'latitude 95' => [new SearchClinicListings(lat: 95.0, lng: 2.35), 'lat', 'La latitude est comprise entre -90 et 90.'];
        yield 'latitude -91' => [new SearchClinicListings(lat: -91.0, lng: 2.35), 'lat', 'La latitude est comprise entre -90 et 90.'];
        yield 'longitude 181' => [new SearchClinicListings(lat: 48.85, lng: 181.0), 'lng', 'La longitude est comprise entre -180 et 180.'];
        yield 'longitude -181' => [new SearchClinicListings(lat: 48.85, lng: -181.0), 'lng', 'La longitude est comprise entre -180 et 180.'];
    }

    #[DataProvider('provideASearchOutOfTheRulesIsRefusedCases')]
    public function testASearchOutOfTheRulesIsRefused(SearchClinicListings $query, string $field, string $message): void
    {
        try {
            ($this->handler)($query);
            self::fail('The search should be refused.');
        } catch (InvalidListingSearchException $e) {
            self::assertSame([$field, $message], [$e->field, $e->getMessage()]);
        }

        self::assertSame([], $this->listings->calls);
    }
}
