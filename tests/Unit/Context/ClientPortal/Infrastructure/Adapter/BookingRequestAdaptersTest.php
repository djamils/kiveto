<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\ClientPortal\Application\Port\BookingDeclinedMessage;
use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;
use App\Context\ClientPortal\Infrastructure\Adapter\Client\CommandBusClientRegistration;
use App\Context\ClientPortal\Infrastructure\Adapter\ClinicListing\QueryBusClinicListings;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Context\ClientPortal\Infrastructure\Adapter\Scheduling\CommandBusOnlineBooking;
use App\Context\ClientPortal\Infrastructure\RateLimit\RateLimiterListingBookingQuota;
use App\Context\ClinicListing\Application\Query\GetClinicListing\ClinicListingDetail;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment\ConfirmNewClientAppointment;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Command\WithdrawPendingOnlineAppointment\WithdrawPendingOnlineAppointment;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\AnsweringQueryBus;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** The slice-7 new-client booking adapters (S7d). */
final class BookingRequestAdaptersTest extends TestCase
{
    public function testTheNewClientCommandsGoToScheduling(): void
    {
        $bus     = new AnsweringCommandBus(static fn (object $command): ?string => $command instanceof BookOnlineAppointment ? 'rdv-1' : null);
        $booking = new CommandBusOnlineBooking($bus);

        self::assertSame('rdv-1', $booking->requestNewClientSlot('clinic-1', 'motif-1', 'vet-1', new \DateTimeImmutable('2026-11-05T14:30:00+01:00')));
        $booking->confirmNewClient('clinic-1', 'rdv-1', 'client-1', 'pet-1');
        $booking->decline('clinic-1', 'rdv-1');
        $booking->withdrawPending('clinic-1', 'rdv-1');
        $booking->confirmNewClient('clinic-1', 'rdv-1', 'client-1', 'pet-1', silent: true);

        [$book, $confirm, $decline, $withdraw, $silent] = $bus->commands;
        self::assertInstanceOf(BookOnlineAppointment::class, $book);
        self::assertSame(['clinic-1', 'motif-1', 'vet-1', null, null, null, true], [$book->clinicId, $book->bookingMotifId, $book->practitionerUserId, $book->ownerId, $book->animalId, $book->createdPetId, $book->newClient]);
        self::assertInstanceOf(ConfirmNewClientAppointment::class, $confirm);
        self::assertSame(['rdv-1', 'clinic-1', 'client-1', 'pet-1', false], [$confirm->appointmentId, $confirm->clinicId, $confirm->ownerId, $confirm->animalId, $confirm->silent]);
        self::assertInstanceOf(ConfirmNewClientAppointment::class, $silent);
        self::assertTrue($silent->silent);
        self::assertInstanceOf(DeclineAppointment::class, $decline);
        self::assertSame(['rdv-1', 'clinic-1'], [$decline->appointmentId, $decline->clinicId]);
        self::assertInstanceOf(WithdrawPendingOnlineAppointment::class, $withdraw);
        self::assertSame(['clinic-1', 'rdv-1'], [$withdraw->clinicId, $withdraw->appointmentId]);
    }

    public function testTheDeclineSmsIsKivetosToTheRequestPhone(): void
    {
        $bus = new AnsweringCommandBus(static fn (object $command): string => 'message-1');

        new CommandBusNotificationRequester($bus)->requestBookingDeclined(new BookingDeclinedMessage('req-1', '+33612345678', 'Sophie Martin', 'Clinique des Lilas', 'jeudi 05/11 à 14h30'));

        $command = $bus->commands[0];
        self::assertInstanceOf(RequestNotification::class, $command);
        self::assertSame(
            ['PORTAL_BOOKING_DECLINED', 'SMS', '+33612345678', 'Sophie Martin', null, null, 'portal_booking_request', 'req-1', 'portal-booking-declined:req-1', ['clinicName' => 'Clinique des Lilas', 'slotLabel' => 'jeudi 05/11 à 14h30']],
            [$command->purpose, $command->channel, $command->recipientAddress, $command->recipientName, $command->clinicId, $command->clientId, $command->subjectType, $command->subjectId, $command->idempotencyKey, $command->parameters],
        );
    }

    public function testANewClientFileHasTheAccountPhoneAsItsMobile(): void
    {
        $bus = new AnsweringCommandBus(static fn (object $command): string => 'client-1');

        self::assertSame('client-1', new CommandBusClientRegistration($bus)->register('clinic-1', 'Sophie', 'Martin', '+33612345678', 'vet-1'));

        $command = $bus->commands[0];
        self::assertInstanceOf(CreateClient::class, $command);
        self::assertSame(['clinic-1', 'Sophie', 'Martin', [], 'staff', 'vet-1', []], [$command->clinicId, $command->firstName, $command->lastName, $command->animals, $command->actorType, $command->actorId, $command->channelsByConsentKind]);
        self::assertSame([['phone', 'mobile', '+33612345678', true]], array_map(static fn ($m): array => [$m->type, $m->label, $m->value, $m->isPrimary], $command->contactMethods));

        // Confirmed by Scheduling (agenda, check-in…): the system opens the file
        new CommandBusClientRegistration($bus)->register('clinic-1', 'Sophie', 'Martin', '+33612345678', null);
        $system = $bus->commands[1];
        self::assertInstanceOf(CreateClient::class, $system);
        self::assertSame(['system', null], [$system->actorType, $system->actorId]);
    }

    public function testAListingResolvesItsClinicServerSide(): void
    {
        $bus = new AnsweringQueryBus([GetClinicListing::class => static fn (GetClinicListing $q): ?ClinicListingDetail => 'listing-1' === $q->listingId
            ? new ClinicListingDetail('listing-1', 'clinic-1', 'Clinique des Lilas', null, null, 'Europe/Paris', [], true, true, [])
            : null]);
        $listings = new QueryBusClinicListings($bus);

        $listing = $listings->listing('listing-1');
        self::assertSame(['listing-1', 'clinic-1', true], [$listing?->listingId, $listing?->clinicId, $listing?->onlineBooking]);
        self::assertNull($listings->listing('listing-2'));
    }

    public function testTenNewClientRequestsPerAccountPer24Hours(): void
    {
        $quota = new RateLimiterListingBookingQuota(new RateLimiterFactory(['id' => 'portal_listing_booking', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '24 hours'], new InMemoryStorage()));

        for ($i = 0; $i < 10; ++$i) {
            $quota->consume('user-1');
        }
        $quota->consume('user-2');

        try {
            $quota->consume('user-1');
            self::fail('The 11th request is refused.');
        } catch (ListingBookingRateLimitedException $e) {
            self::assertGreaterThan(0, $e->retryAfterSeconds);
        }
    }
}
