<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 7, the public clinic directory (Task 49): search (AC 77, AC 270),
 * card, and the new-client booking reads (AC 82), the per-IP quota (AC 83).
 * Every call is made without token. The linked clinic of the slice-4 setup
 * (« Clinique des Lilas », Paris) is the listed clinic open to new clients;
 * today is Monday 2 November 2026, 11:00 in Paris.
 */
final class ClinicListingsTest extends BookingApiTestCase
{
    private const string LILAS = '01930000-0000-7000-8000-0000000e7001';

    // Bellecour, Lyon
    private const float LAT = 45.757814;
    private const float LNG = 4.832011;

    /** key => [listing id, clinic id, name, city, lat, lng, listed, active, accepts new clients] */
    private const array OTHERS = [
        'port'      => ['01930000-0000-7000-8000-0000000e7002', self::OTHER_CLINIC, 'Clinique du Port', 'Lyon', 45.760000, 4.835000, true, true, true],
        'lyonnais'  => ['01930000-0000-7000-8000-0000000e7003', '01930000-0000-7000-8000-0000000e7c03', 'Cabinet Lyonnais', 'Caluire', null, null, true, true, true],
        'tonkin'    => ['01930000-0000-7000-8000-0000000e7004', '01930000-0000-7000-8000-0000000e7c04', 'Clinique du Tonkin', 'Villeurbanne', 45.771000, 4.880000, true, true, true],
        'unlisted'  => ['01930000-0000-7000-8000-0000000e7005', '01930000-0000-7000-8000-0000000e7c05', 'Clinique Lyon Sud', 'Lyon', 45.700000, 4.820000, false, true, true],
        'suspended' => ['01930000-0000-7000-8000-0000000e7006', '01930000-0000-7000-8000-0000000e7c06', 'Clinique Lyon Nord', 'Lyon', 45.790000, 4.830000, true, false, true],
        'percent'   => ['01930000-0000-7000-8000-0000000e7007', '01930000-0000-7000-8000-0000000e7c07', 'Clinique 100% Chats', 'Marseille', 43.296000, 5.369000, true, true, true],
        'thousand'  => ['01930000-0000-7000-8000-0000000e7008', '01930000-0000-7000-8000-0000000e7c08', 'Clinique 1000 Chats', 'Marseille', 43.297000, 5.370000, true, true, true],
    ];

    private string $vet;

    private string $consultation;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.170';
        parent::setUp();

        $this->listing(self::LILAS, self::CLINIC, 'Clinique des Lilas', '18 rue Lepic', '75018', 'Paris', 48.884700, 2.333300, true, true, true, ['Chats', 'NAC']);
        foreach (self::OTHERS as [$id, $clinicId, $name, $city, $lat, $lng, $listed, $active, $accepts]) {
            $this->listing($id, $clinicId, $name, '1 place Bellecour', '69002', $city, $lat, $lng, $listed, $active, $accepts, []);
        }

        $this->vet          = $this->veterinarian('Claire', 'Rousseau');
        $this->consultation = $this->newClientMotif('Première consultation', [$this->vet]);
        $this->takeNewClients();
        // Clinique du Port takes online bookings, but not from new clients
        $this->takeNewClients(self::OTHER_CLINIC, newClients: false);
    }

    /** AC 77: by name or city, only the listed active clinics, with what the card needs. */
    public function testASearchByNameOrCityListsOnlyTheListedActiveClinics(): void
    {
        $page = self::json($this->get('/clinic-listings?q=lyon'), 200);

        self::assertSame(['Cabinet Lyonnais', 'Clinique du Port'], self::names($page));
        self::assertNull($page['nextCursor']);
        self::assertSame(
            [
                'id'              => self::OTHERS['port'][0],
                'name'            => 'Clinique du Port',
                'address'         => ['line' => '1 place Bellecour', 'postalCode' => '69002', 'city' => 'Lyon'],
                'phone'           => '+33478000000',
                'distanceM'       => null,
                'onKiveto'        => true,
                'onlineBooking'   => false,
                'tags'            => [],
                'nextAvailableAt' => null,
            ],
            self::items($page)[1],
        );

        $lilas = self::items(self::json($this->get('/clinic-listings?q=lepic'), 200));
        self::assertSame([], $lilas, 'the street is not searched');

        $lilas = self::items(self::json($this->get('/clinic-listings?q=' . rawurlencode('  des   lilas ')), 200))[0] ?? null;
        self::assertIsArray($lilas);
        self::assertSame([self::LILAS, true, ['Chats', 'NAC']], [$lilas['id'], $lilas['onlineBooking'], $lilas['tags']]);
    }

    /** AC 77: `q` needs 2 characters; `%` and `_` are taken literally. */
    public function testAOneCharacterQueryIsRefusedAndAPercentIsLiteral(): void
    {
        $problem = self::json($this->get('/clinic-listings?q=l'), 422);
        self::assertSame('validation_failed', $problem['type']);
        self::assertSame('q', self::firstViolationPath($problem));
        self::assertSame('Saisissez au moins 2 caractères.', self::rows($problem['violations'] ?? null)[0]['message'] ?? null);

        self::assertSame(['Clinique 100% Chats'], self::names(self::json($this->get('/clinic-listings?q=100%25'), 200)));
        self::assertSame(['Clinique 100% Chats', 'Clinique 1000 Chats'], self::names(self::json($this->get('/clinic-listings?q=100'), 200)));
    }

    /** AC 77: around a point, nearest first with the distance; a clinic without coordinates is absent from that result only. */
    public function testAroundAPointTheNearestComeFirstWithoutTheUnplacedClinics(): void
    {
        $first = self::json($this->get(\sprintf('/clinic-listings?lat=%s&lng=%s&limit=1', self::LAT, self::LNG)), 200);
        self::assertSame(['Clinique du Port'], self::names($first));
        $distance = self::items($first)[0]['distanceM'];
        self::assertIsInt($distance);
        self::assertGreaterThan(200, $distance);
        self::assertLessThan(500, $distance);
        self::assertIsString($first['nextCursor']);

        $second = self::json($this->get(\sprintf('/clinic-listings?lat=%s&lng=%s&limit=1&cursor=%s', self::LAT, self::LNG, $first['nextCursor'])), 200);
        self::assertSame(['Clinique du Tonkin'], self::names($second));
        self::assertNull($second['nextCursor'], 'Cabinet Lyonnais has no coordinates; Paris and Marseille are too far');

        // Without the point, the unplaced clinic is listed
        self::assertContains('Cabinet Lyonnais', self::names(self::json($this->get('/clinic-listings?limit=50'), 200)));
    }

    /** AC 270: a cursor is bound to its coordinates; coordinates out of range are a validation failure. */
    public function testACursorOfOtherCoordinatesAndAnOutOfRangeLatitudeAreRefused(): void
    {
        $cursor = self::json($this->get(\sprintf('/clinic-listings?lat=%s&lng=%s&limit=1', self::LAT, self::LNG)), 200)['nextCursor'];
        self::assertIsString($cursor);

        self::assertSame('cursor_invalid', self::problemType($this->get('/clinic-listings?lat=48.85&lng=2.35&cursor=' . $cursor), 400));
        self::assertSame('cursor_invalid', self::problemType($this->get('/clinic-listings?cursor=' . $cursor), 400), 'a distance cursor for a name ordering');
        self::assertSame('cursor_invalid', self::problemType($this->get('/clinic-listings?cursor=forged'), 400));

        foreach (['lat=95&lng=2' => 'lat', 'lat=45&lng=-181' => 'lng', 'lat=45' => 'lng', 'lat=nord&lng=2' => 'lat'] as $query => $field) {
            $problem = self::json($this->get('/clinic-listings?' . $query), 422);
            self::assertSame('validation_failed', $problem['type'], $query);
            self::assertSame($field, self::firstViolationPath($problem), $query);
        }
    }

    public function testWithoutQueryTheClinicsComePageAfterPageByName(): void
    {
        $first = self::json($this->get('/clinic-listings?limit=3'), 200);
        self::assertSame(['Cabinet Lyonnais', 'Clinique 100% Chats', 'Clinique 1000 Chats'], self::names($first));
        self::assertIsString($first['nextCursor']);

        $second = self::json($this->get('/clinic-listings?limit=3&cursor=' . $first['nextCursor']), 200);
        self::assertSame(['Clinique des Lilas', 'Clinique du Port', 'Clinique du Tonkin'], self::names($second));
        self::assertNull($second['nextCursor']);
    }

    /** Like every cursor route: 20 by default, above 50 brought back to 50, below 1 to 1. */
    public function testThePageSizeIsClampedNotRefused(): void
    {
        self::assertCount(6, self::names(self::json($this->get('/clinic-listings?limit=51'), 200)));
        self::assertCount(6, self::names(self::json($this->get('/clinic-listings?limit=500'), 200)));

        $smallest = self::json($this->get('/clinic-listings?limit=0'), 200);
        self::assertSame(['Cabinet Lyonnais'], self::names($smallest));
        self::assertIsString($smallest['nextCursor']);
    }

    public function testTheCardOfAListedClinic(): void
    {
        self::assertSame(
            [
                'id'                => self::LILAS,
                'name'              => 'Clinique des Lilas',
                'address'           => ['line' => '18 rue Lepic', 'postalCode' => '75018', 'city' => 'Paris'],
                'phone'             => '+33478000000',
                'timezone'          => 'Europe/Paris',
                'openingHours'      => [['day' => 1, 'opensAt' => '08:30', 'closesAt' => '12:30'], ['day' => 1, 'opensAt' => '14:00', 'closesAt' => '19:00']],
                'acceptsNewClients' => true,
                'onlineBooking'     => true,
                'tags'              => ['Chats', 'NAC'],
            ],
            self::json($this->get('/clinic-listings/' . self::LILAS), 200),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryListingRouteHidesAnUnlistedInactiveOrUnknownClinicCases(): iterable
    {
        yield 'card' => [''];
        yield 'motifs' => ['/booking/motifs'];
        yield 'practitioners' => ['/booking/practitioners?motifId=01930000-0000-7000-8000-0000000000aa'];
        yield 'availability' => ['/booking/availability?motifId=01930000-0000-7000-8000-0000000000aa&practitionerId=01930000-0000-7000-8000-0000000000bb&from=2026-11-02&to=2026-11-03'];
    }

    #[DataProvider('provideEveryListingRouteHidesAnUnlistedInactiveOrUnknownClinicCases')]
    public function testEveryListingRouteHidesAnUnlistedInactiveOrUnknownClinic(string $path): void
    {
        foreach ([self::OTHERS['unlisted'][0], self::OTHERS['suspended'][0], Uuid::v7()->toRfc4122(), 'lilas', self::CLINIC] as $listingId) {
            self::assertSame('not_found', self::problemType($this->get('/clinic-listings/' . $listingId . $path), 404), $listingId);
        }
    }

    /** AC 82: a clinic not open to new clients online has `onlineBooking: false` and its booking routes answer booking_not_open. */
    public function testTheBookingRoutesOfAClinicNotOpenToNewClientsAreNotOpen(): void
    {
        $port = self::OTHERS['port'][0];
        self::assertFalse(self::json($this->get('/clinic-listings/' . $port), 200)['onlineBooking']);

        foreach (self::bookingPathsOf($port, Uuid::v7()->toRfc4122(), $this->vet) as $path) {
            self::assertSame('booking_not_open', self::problemType($this->get($path), 422), $path);
        }

        // The Lilas stops accepting new clients: the same, though Scheduling still takes them
        $settings = ListingSettings::defaults(self::CLINIC);
        $settings->update(true, false, ['Chats', 'NAC']);
        $this->service(ListingSettingsRepositoryInterface::class)->save($settings);

        $card = self::json($this->get('/clinic-listings/' . self::LILAS), 200);
        self::assertSame([false, false], [$card['acceptsNewClients'], $card['onlineBooking']]);
        foreach (self::bookingPathsOf(self::LILAS, $this->consultation, $this->vet) as $path) {
            self::assertSame('booking_not_open', self::problemType($this->get($path), 422), $path);
        }
    }

    public function testOnlyTheMotifsOpenOnlineToNewClientsAreListed(): void
    {
        $first = $this->newClientMotif('Bilan NAC', [$this->vet], sortOrder: -1);
        $this->newClientMotif('Vaccin', [$this->vet], newClients: false);
        $this->newClientMotif('Chirurgie', [$this->vet], online: false);
        $this->motif('Rappel', [$this->vet]);

        self::assertSame(
            [
                ['id' => $first, 'label' => 'Bilan NAC', 'description' => null, 'durationMin' => 30, 'suggested' => false, 'suggestionReason' => null],
                ['id' => $this->consultation, 'label' => 'Première consultation', 'description' => null, 'durationMin' => 30, 'suggested' => false, 'suggestionReason' => null],
            ],
            self::list($this->get('/clinic-listings/' . self::LILAS . '/booking/motifs')),
        );
    }

    public function testThePractitionersOfAMotifOpenToNewClients(): void
    {
        self::assertSame(
            [['id' => $this->vet, 'displayName' => 'Dr Claire Rousseau']],
            self::list($this->get('/clinic-listings/' . self::LILAS . '/booking/practitioners?motifId=' . $this->consultation)),
        );

        $knownOnly = $this->newClientMotif('Vaccin', [$this->vet], newClients: false);
        foreach ([$knownOnly, Uuid::v7()->toRfc4122(), 'nope'] as $motifId) {
            self::assertSame('not_found', self::problemType($this->get('/clinic-listings/' . self::LILAS . '/booking/practitioners?motifId=' . $motifId), 404), $motifId);
        }
        self::assertSame('validation_failed', self::problemType($this->get('/clinic-listings/' . self::LILAS . '/booking/practitioners'), 422));
    }

    /** A new client always waits for the clinic: only the slots past the manual lead time (24 h). */
    public function testTheAvailabilityOfANewClientStartsAfterTheManualLeadTime(): void
    {
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-02', '09:00', '14:30');
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-03', '09:00', '12:30');

        $days = self::rows(self::json($this->get(\sprintf(
            '/clinic-listings/%s/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-02&to=2026-11-03',
            self::LILAS,
            $this->consultation,
            $this->vet,
        )), 200)['days'] ?? null);

        self::assertSame(
            [
                ['date' => '2026-11-02', 'open' => true, 'slots' => []],
                ['date' => '2026-11-03', 'open' => true, 'slots' => [
                    ['startAt' => '2026-11-03T11:00:00+01:00'],
                    ['startAt' => '2026-11-03T11:15:00+01:00'],
                    ['startAt' => '2026-11-03T11:30:00+01:00'],
                    ['startAt' => '2026-11-03T11:45:00+01:00'],
                    ['startAt' => '2026-11-03T12:00:00+01:00'],
                ]],
            ],
            $days,
        );
    }

    public function testTheAvailabilityRefusesWhatTheClinicDoesNotOfferToNewClients(): void
    {
        $knownOnly = $this->newClientMotif('Vaccin', [$this->vet], newClients: false);
        $path      = '/clinic-listings/' . self::LILAS . '/booking/availability?motifId=%s&practitionerId=%s&from=%s&to=2026-11-03';

        self::assertSame('not_found', self::problemType($this->get(\sprintf($path, $knownOnly, $this->vet, '2026-11-02')), 404));
        self::assertSame('not_found', self::problemType($this->get(\sprintf($path, $this->consultation, Uuid::v7()->toRfc4122(), '2026-11-02')), 404));

        $problem = self::json($this->get(\sprintf($path, $this->consultation, $this->vet, '2026-11-01')), 422);
        self::assertSame(['validation_failed', 'from'], [$problem['type'], self::firstViolationPath($problem)]);
    }

    /** AC 83: 120 requests a minute per client IP over every listing route, then 429 with Retry-After. */
    public function testTheDirectoryAnswersOneHundredTwentyRequestsAMinutePerIp(): void
    {
        $this->clientIp = '203.0.113.171';
        $limiter        = $this->browser->getContainer()->get('test.limiter.portal_public_listing');
        \assert($limiter instanceof RateLimiterFactoryInterface);
        $limiter->create('203.0.113.171')->consume(118);

        self::json($this->get('/clinic-listings?q=lyon'), 200);
        self::json($this->get('/clinic-listings/' . self::LILAS), 200);

        foreach (['/clinic-listings', '/clinic-listings/' . self::LILAS, ...self::bookingPathsOf(self::LILAS, $this->consultation, $this->vet)] as $path) {
            $refused = $this->get($path);
            self::assertSame('rate_limited', self::problemType($refused, 429), $path);
            self::assertGreaterThan(0, (int) $refused->headers->get('Retry-After'));
        }

        $this->clientIp = '203.0.113.172';
        self::json($this->get('/clinic-listings'), 200);
    }

    /** The clinic id never leaves the server, whatever the route. */
    public function testNoAnswerNamesAClinicId(): void
    {
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-03', '09:00', '12:30');
        $clinicIds = [self::CLINIC, ...array_column(self::OTHERS, 1)];

        $responses = [
            $this->get('/clinic-listings'),
            $this->get(\sprintf('/clinic-listings?lat=%s&lng=%s', self::LAT, self::LNG)),
            $this->get('/clinic-listings/' . self::LILAS),
            $this->get('/clinic-listings/' . self::OTHERS['port'][0]),
            ...array_map(fn (string $path): Response => $this->get($path), self::bookingPathsOf(self::LILAS, $this->consultation, $this->vet)),
        ];

        foreach ($responses as $response) {
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            foreach ($clinicIds as $clinicId) {
                self::assertStringNotContainsString($clinicId, (string) $response->getContent());
            }
        }
    }

    protected function phone(): string
    {
        return '+33615000701';
    }

    /**
     * @param list<string> $tags
     */
    private function listing(
        string $id,
        string $clinicId,
        string $name,
        string $street,
        string $postalCode,
        string $city,
        ?float $lat,
        ?float $lng,
        bool $listed,
        bool $active,
        bool $acceptsNewClients,
        array $tags,
    ): void {
        $profile = new ListingProfile(
            $name,
            ListingAddress::fromParts($street, $postalCode, $city),
            '+33478000000',
            'Europe/Paris',
            [['day' => 1, 'opensAt' => '08:30', 'closesAt' => '12:30'], ['day' => 1, 'opensAt' => '14:00', 'closesAt' => '19:00']],
            $active,
        );
        $this->service(ClinicListingRepositoryInterface::class)->save(ClinicListing::reconstitute(
            ClinicListingId::fromString($id),
            $clinicId,
            $profile,
            null === $lat || null === $lng ? null : ListingCoordinates::fromDegrees($lat, $lng),
            $profile->address()?->hash(),
            new \DateTimeImmutable('2026-11-02 09:00:00'),
        ));

        $settings = ListingSettings::defaults($clinicId);
        $settings->update($listed, $acceptsNewClients, $tags);
        $this->service(ListingSettingsRepositoryInterface::class)->save($settings);
    }

    /** Online booking enabled (lead time 2 h, manual lead time 24 h), new clients taken or not. */
    private function takeNewClients(string $clinicId = self::CLINIC, bool $newClients = true): void
    {
        $this->service(OnlineBookingSettingsRepositoryInterface::class)->save(
            new OnlineBookingSettings(ClinicId::fromString($clinicId), true, 15, 120, 24, 60, 24, $newClients),
        );
    }

    /**
     * @param list<string> $practitionerIds
     */
    private function newClientMotif(string $label, array $practitionerIds, bool $newClients = true, bool $online = true, int $sortOrder = 0): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->service(BookingMotifRepositoryInterface::class)->save(BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            null,
            30,
            $online,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            array_map(UserId::fromString(...), $practitionerIds),
            null,
            $newClients,
            $sortOrder,
        ));

        return $id;
    }

    /**
     * @return list<string>
     */
    private static function bookingPathsOf(string $listingId, string $motifId, string $practitionerId): array
    {
        return [
            '/clinic-listings/' . $listingId . '/booking/motifs',
            '/clinic-listings/' . $listingId . '/booking/practitioners?motifId=' . $motifId,
            \sprintf('/clinic-listings/%s/booking/availability?motifId=%s&practitionerId=%s&from=2026-11-03&to=2026-11-04', $listingId, $motifId, $practitionerId),
        ];
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<array<string, mixed>>
     */
    private static function items(array $page): array
    {
        return self::rows($page['items'] ?? null);
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<mixed>
     */
    private static function names(array $page): array
    {
        return array_column(self::items($page), 'name');
    }
}
