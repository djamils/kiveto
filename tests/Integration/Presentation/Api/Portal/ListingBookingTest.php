<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestId;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingAddress;
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
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * S7d through HTTP (AC 79, 82, 260, 264, 265, 268 API side): an owner
 * account without link to the Lilas clinic, listed in the directory and
 * taking new clients online (manual lead time 24 h); Wednesday 4 November
 * 09:00–12:00 is a consultation block of Dr Lambert. Now: Monday
 * 2 November 2026, 11:00 in Paris.
 */
final class ListingBookingTest extends BookingApiTestCase
{
    private const string LISTING = '01930000-0000-7000-8000-0000000e4801';
    private const string SLOT    = '2026-11-04T10:00:00+01:00';

    private string $vet;
    private string $motifId;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.148';
        parent::setUp();

        // A new client: the slice-3 link of the base case is not there
        $this->connection()->executeStatement('DELETE FROM client_portal__client_links');
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::register($this->userId, 'Sophie', 'Martin', 'v1', new \DateTimeImmutable('2026-10-01')));

        $this->listing(self::LISTING, self::CLINIC, acceptsNewClients: true);
        $this->takeNewClients(true);
        $this->vet     = $this->veterinarian('Claire', 'Lambert');
        $this->motifId = $this->newClientMotif('Première consultation', [$this->vet]);
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-04', '09:00', '12:00');
    }

    public function testANewClientBooksThroughTheDirectoryWithoutLinkAndAReplayBooksNothingMore(): void
    {
        // AC 79
        $view = self::json($this->book('key-79'), 201);

        self::assertSame(
            ['clinic' => ['name' => 'Clinique des Lilas', 'city' => 'Paris'], 'pet' => ['name' => 'Pixel', 'species' => 'cat'], 'motif' => 'Première consultation', 'startAt' => self::SLOT, 'status' => 'pending', 'declineReason' => null, 'appointmentId' => null],
            array_diff_key($view, ['id' => true]),
        );
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__booking_requests'));
        self::assertSame(0, $this->number('SELECT COUNT(*) FROM client_portal__client_links'));
        // The slot is held by a pending appointment without owner nor animal
        self::assertSame(
            [AppointmentStatus::PENDING_CLINIC_CONFIRMATION->value, null, null, 'portal'],
            array_values($this->connection()->fetchAssociative('SELECT status, owner_id, animal_id, source FROM scheduling__appointments') ?: []),
        );

        // Same key again: the same answer, nothing new
        $replay = self::json($this->book('key-79'), 201);
        self::assertSame([$view['id'], 'pending'], [$replay['id'] ?? null, $replay['status'] ?? null]);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__booking_requests'));

        $list = self::json($this->authGet('/me/booking-requests'), 200);
        self::assertSame([$view['id']], array_column(self::rowsOf($list['items']), 'id'));
        self::assertNull($list['nextCursor']);
        // The appointment is no business of `/clinics/{c}` yet
        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/appointments')), 404));
    }

    public function testWithoutTokenTheBookingIsUnauthorized(): void
    {
        // AC 260
        self::assertSame('unauthorized', self::problemType($this->post('/clinic-listings/' . self::LISTING . '/booking/appointments', self::body(), ['HTTP_IDEMPOTENCY_KEY' => 'k']), 401));
    }

    public function testAnAccountAlreadyLinkedBooksAsAClient(): void
    {
        // AC 264
        $this->link(self::CLIENT);

        self::assertSame('already_linked', self::problemType($this->book('key-264'), 422));
    }

    public function testOnePendingRequestPerClinicAndThreeInAll(): void
    {
        // AC 265
        self::json($this->book('key-265-a'), 201);
        self::assertSame('booking_request_limit', self::problemType($this->book('key-265-b', '2026-11-04T11:00:00+01:00'), 422));

        $this->connection()->executeStatement('DELETE FROM client_portal__booking_requests');
        foreach (['01', '02', '03'] as $n) {
            $this->service(BookingRequestRepositoryInterface::class)->save(BookingRequest::request(
                BookingRequestId::fromString('01930000-0000-7000-8000-0000000e48' . $n),
                $this->userId,
                '01930000-0000-7000-8000-0000000e4c' . $n,
                self::LISTING,
                Uuid::v7()->toRfc4122(),
                'Consultation',
                new \DateTimeImmutable('2026-11-06 09:00'),
                'Pixel',
                'cat',
                'Sophie',
                'Martin',
                $this->phone(),
                new \DateTimeImmutable('2026-11-01 09:00'),
            ));
        }

        self::assertSame('booking_request_limit', self::problemType($this->book('key-265-c', '2026-11-04T11:00:00+01:00'), 422));
    }

    public function testAClinicTakingNoNewClientIsNotOpen(): void
    {
        // AC 82: Scheduling takes no new client…
        $this->takeNewClients(false);
        self::assertSame('booking_not_open', self::problemType($this->book('key-82-a'), 422));

        // …or the clinic does not accept new clients in the directory
        $this->takeNewClients(true);
        $this->listing(self::LISTING, self::CLINIC, acceptsNewClients: false);
        self::assertSame('booking_not_open', self::problemType($this->book('key-82-b'), 422));
    }

    public function testAnUnlistedListingAnUnknownMotifAndATakenSlot(): void
    {
        self::assertSame('not_found', self::problemType($this->book('key-404', listingId: '01930000-0000-7000-8000-0000000e4899'), 404));
        self::assertSame('not_found', self::problemType($this->book('key-motif', motifId: Uuid::v7()->toRfc4122()), 404));
        // Before the manual lead time: not offered
        self::assertSame('slot_taken', self::problemType($this->book('key-409', '2026-11-03T10:00:00+01:00'), 409));
        self::assertSame('validation_failed', self::problemType($this->authPost('/clinic-listings/' . self::LISTING . '/booking/appointments', ['newPet' => ['name' => 'Pixel', 'species' => 'lion']] + self::body(), 'key-422'), 422));
    }

    public function testTenRequestsADayAtMost(): void
    {
        $limiter = $this->browser->getContainer()->get('test.limiter.portal_listing_booking');
        \assert($limiter instanceof RateLimiterFactoryInterface);
        $limiter->create($this->userId)->consume(10);

        $response = $this->book('key-429');

        self::assertSame('rate_limited', self::problemType($response, 429));
        self::assertNotNull($response->headers->get('Retry-After'));
    }

    public function testTheRequestsArePagedNewestFirst(): void
    {
        foreach (['01', '02', '03'] as $n) {
            $this->service(BookingRequestRepositoryInterface::class)->save(BookingRequest::request(
                BookingRequestId::fromString('01930000-0000-7000-8000-0000000e48' . $n),
                $this->userId,
                self::CLINIC,
                self::LISTING,
                Uuid::v7()->toRfc4122(),
                'Consultation',
                new \DateTimeImmutable('2026-11-06 09:00'),
                'Pixel',
                'cat',
                'Sophie',
                'Martin',
                $this->phone(),
                new \DateTimeImmutable('2026-11-01 ' . $n . ':00'),
            ));
        }

        $first = self::json($this->authGet('/me/booking-requests?limit=2'), 200);
        self::assertSame(['01930000-0000-7000-8000-0000000e4803', '01930000-0000-7000-8000-0000000e4802'], array_column(self::rowsOf($first['items']), 'id'));
        self::assertIsString($first['nextCursor']);

        $next = self::json($this->authGet('/me/booking-requests?limit=2&cursor=' . $first['nextCursor']), 200);
        self::assertSame(['01930000-0000-7000-8000-0000000e4801'], array_column(self::rowsOf($next['items']), 'id'));
        self::assertSame('2026-11-06T10:00:00+01:00', self::rowsOf($next['items'])[0]['startAt'] ?? null);

        self::assertSame('cursor_invalid', self::problemType($this->authGet('/me/booking-requests?cursor=nope'), 400));
    }

    protected function phone(): string
    {
        return '+33615004801';
    }

    private function book(string $key, string $startAt = self::SLOT, string $listingId = self::LISTING, ?string $motifId = null): \Symfony\Component\HttpFoundation\Response
    {
        return $this->authPost('/clinic-listings/' . $listingId . '/booking/appointments', self::body($startAt, $motifId ?? $this->motifId, $this->vet ?? ''), $key);
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(string $startAt = self::SLOT, string $motifId = '01930000-0000-7000-8000-0000000e4fff', string $practitionerId = '01930000-0000-7000-8000-0000000e4ffe'): array
    {
        return ['newPet' => ['name' => 'Pixel', 'species' => 'cat'], 'motifId' => $motifId, 'practitionerId' => $practitionerId, 'startAt' => $startAt];
    }

    private function listing(string $id, string $clinicId, bool $acceptsNewClients): void
    {
        $profile = new ListingProfile('Clinique des Lilas', ListingAddress::fromParts('18 rue Lepic', '75018', 'Paris'), null, 'Europe/Paris', [], true);
        $this->service(ClinicListingRepositoryInterface::class)->save(ClinicListing::reconstitute(
            ClinicListingId::fromString($id),
            $clinicId,
            $profile,
            null,
            $profile->address()?->hash(),
            new \DateTimeImmutable('2026-11-02 09:00:00'),
        ));
        $settings = ListingSettings::defaults($clinicId);
        $settings->update(true, $acceptsNewClients, []);
        $this->service(ListingSettingsRepositoryInterface::class)->save($settings);
    }

    private function takeNewClients(bool $newClients): void
    {
        $this->service(OnlineBookingSettingsRepositoryInterface::class)->save(
            new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), true, 15, 120, 24, 60, 24, $newClients),
        );
    }

    /**
     * @param list<string> $practitionerIds
     */
    private function newClientMotif(string $label, array $practitionerIds): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->service(BookingMotifRepositoryInterface::class)->save(BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            null,
            30,
            true,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            array_map(UserId::fromString(...), $practitionerIds),
            null,
            true,
            0,
        ));

        return $id;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function rowsOf(mixed $rows): array
    {
        self::assertIsArray($rows);

        return array_values(array_filter($rows, \is_array(...)));
    }
}
