<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\BookingRequest;

use App\Context\ClientPortal\Application\Command\RequestListingBooking\RequestListingBooking;
use App\Context\ClientPortal\Application\Port\ListingForBooking;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Exception\AlreadyLinkedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestLimitException;
use App\Context\ClientPortal\Domain\Exception\InvalidNewPetException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingNotOpenException;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use App\Context\ClientPortal\Domain\Exception\ListingNotFoundException;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakeOnlineBooking;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use PHPUnit\Framework\TestCase;

/** S7d: the checks of a new-client booking, in their order, then the request held with the slot. */
final class RequestListingBookingHandlerTest extends TestCase
{
    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
    }

    public function testTheSlotIsHeldAndTheRequestRecordedWithoutLink(): void
    {
        $id = $this->book();

        $request = $this->world->requests->requests[$id] ?? self::fail('No request.');
        self::assertSame(
            [W::USER, W::CLINIC, W::LISTING, FakeOnlineBooking::APPOINTMENT, 'Première consultation', '2026-11-05T13:30:00+00:00', 'Pixel', 'cat', 'Sophie', 'Martin', W::PHONE, BookingRequestStatus::PENDING, '2026-11-02T10:00:00+00:00'],
            [$request->userId(), $request->clinicId(), $request->listingId(), $request->appointmentId(), $request->motifLabel(), $request->startAt()->format('c'), $request->newPetName(), $request->newPetSpecies(), $request->requesterFirstName(), $request->requesterLastName(), $request->requesterPhone(), $request->status(), $request->createdAt()->format('c')],
        );
        self::assertSame([['request', W::CLINIC, W::MOTIF, W::VET, '2026-11-05T14:30:00+01:00']], $this->world->booking->calls);
        self::assertSame([W::USER], $this->world->quotaCalls);
        self::assertSame(['user:' . W::USER], $this->world->requests->lockedForUpdate);
        self::assertSame([], $this->world->links->links);
    }

    public function testAMotifNoLongerListedIsNamedPlainly(): void
    {
        $this->world->motifs = [];

        self::assertSame('Rendez-vous', $this->world->requests->requests[$this->book()]->motifLabel());
    }

    public function testTheNewPetIsChecked(): void
    {
        foreach ([['  ', 'cat', InvalidNewPetException::NAME], [str_repeat('a', 81), 'cat', InvalidNewPetException::NAME], ['Pixel', 'lion', InvalidNewPetException::SPECIES]] as [$name, $species, $field]) {
            try {
                $this->book($name, $species);
                self::fail('The pet should be refused.');
            } catch (InvalidNewPetException $e) {
                self::assertSame($field, $e->field);
            }
        }

        self::assertSame([], $this->world->booking->calls);
    }

    public function testAnUnlistedListingIsNotFound(): void
    {
        $this->expectException(ListingNotFoundException::class);

        $this->book(listingId: '01960000-0000-7000-8000-0000000c2fff');
    }

    public function testAnAccountAlreadyLinkedBooksThroughItsClinic(): void
    {
        $this->world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c7001'), W::USER, W::CLINIC, W::CLIENT, W::utc('2026-10-01 08:00:00')));

        $this->expectException(AlreadyLinkedException::class);

        $this->book();
    }

    public function testOnePendingRequestPerClinicAndThreeInAll(): void
    {
        $this->world->pending('01960000-0000-7000-8000-0000000d0001');

        try {
            $this->book();
            self::fail('A second request at the clinic is refused.');
        } catch (BookingRequestLimitException $e) {
            self::assertSame(BookingRequestLimitException::CLINIC, $e->limit);
        }

        $this->world->requests->requests = [];
        foreach (['01', '02', '03'] as $suffix) {
            $this->world->pending('01960000-0000-7000-8000-0000000d00' . $suffix, '01960000-0000-7000-8000-0000000c10' . $suffix . '9');
        }

        try {
            $this->book();
            self::fail('A fourth request is refused.');
        } catch (BookingRequestLimitException $e) {
            self::assertSame(BookingRequestLimitException::OVERALL, $e->limit);
        }

        // Counted before the quota: nothing consumed
        self::assertSame([], $this->world->quotaCalls);
    }

    public function testTheDailyQuotaComesBeforeTheAvailability(): void
    {
        $this->world->quotaRetryAfter = 3600;

        try {
            $this->book();
            self::fail('The quota is used up.');
        } catch (ListingBookingRateLimitedException $e) {
            self::assertSame(3600, $e->retryAfterSeconds);
        }

        self::assertSame([], $this->world->booking->calls);
    }

    public function testAListingClosedToNewClientsIsNotOpen(): void
    {
        $this->world->listings[W::LISTING] = new ListingForBooking(W::LISTING, W::CLINIC, false);

        try {
            $this->book();
            self::fail('The listing takes no new client.');
        } catch (ListingBookingNotOpenException $e) {
            self::assertSame(W::LISTING, $e->listingId);
        }

        self::assertSame([W::USER], $this->world->quotaCalls);
    }

    public function testSchedulingRefusalsPassThroughAndNothingIsRecorded(): void
    {
        $this->world->booking->refusal = new \DomainException('slot taken');

        try {
            $this->book();
            self::fail('The slot is taken.');
        } catch (\DomainException $e) {
            self::assertSame('slot taken', $e->getMessage());
        }

        self::assertSame([], $this->world->requests->requests);
    }

    public function testAnAccountWithoutProfileOrNoLongerActiveBooksNothing(): void
    {
        $this->world->profiles = new InMemoryOwnerProfiles();

        try {
            $this->book();
            self::fail('No profile.');
        } catch (OwnerProfileNotFoundException) {
        }

        $this->world          = new W();
        $this->world->account = null;

        $this->expectException(\LogicException::class);

        $this->book();
    }

    private function book(string $name = ' Pixel ', string $species = 'cat', string $listingId = W::LISTING): string
    {
        return $this->world->requestHandler()(new RequestListingBooking(W::USER, $listingId, $name, $species, W::MOTIF, W::VET, new \DateTimeImmutable('2026-11-05T14:30:00+01:00')));
    }
}
