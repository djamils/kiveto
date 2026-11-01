<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\BookingRequest;

use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequest;
use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointment;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Service\BookingDeclineNotice;
use App\Context\ClientPortal\Application\Service\BookingRequestAccountErasure;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AC 81, AC 90, AC 269: what ends a request, and the one SMS that tells the new client. */
final class DeclineAndSyncBookingRequestTest extends TestCase
{
    private const string REQUEST     = '01960000-0000-7000-8000-0000000d0001';
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000a0001';

    private W $world;

    protected function setUp(): void
    {
        $this->world = new W();
        $this->world->pending(self::REQUEST, appointmentId: self::APPOINTMENT);
    }

    public function testStaffDeclineFreesTheSlotAndTellsTheOwner(): void
    {
        $this->world->declineHandler()(new DeclineBookingRequest(W::CLINIC, self::REQUEST, W::VET));

        self::assertSame([['decline', W::CLINIC, self::APPOINTMENT]], $this->world->booking->calls);
        $request = $this->world->requests->requests[self::REQUEST];
        self::assertSame([BookingRequestStatus::DECLINED, DeclineReason::DECLINED], [$request->status(), $request->declineReason()]);

        [$message] = $this->world->fakes->bookingDeclines;
        // Thursday 5 November, 13:30 UTC = 14:30 in Lyon
        self::assertSame([self::REQUEST, W::PHONE, 'Sophie Martin', 'Clinique des Lilas', 'jeudi 05/11 à 14h30'], [$message->requestId, $message->phone, $message->recipientName, $message->clinicName, $message->slotLabel]);

        // A second answer finds it resolved
        $this->expectException(BookingRequestAlreadyResolvedException::class);
        $this->world->declineHandler()(new DeclineBookingRequest(W::CLINIC, self::REQUEST, W::VET));
    }

    public function testAnotherClinicsRequestIsNotFound(): void
    {
        $this->expectException(BookingRequestNotFoundException::class);

        $this->world->declineHandler()(new DeclineBookingRequest(W::CLINIC_2, self::REQUEST, W::VET));
    }

    /**
     * @return iterable<string, array{string, DeclineReason}>
     */
    public static function provideSchedulingsDeclineReachesTheRequestOnceCases(): iterable
    {
        yield 'declined' => [SyncBookingRequestFromAppointment::DECLINED, DeclineReason::DECLINED];
        yield 'expired' => [SyncBookingRequestFromAppointment::EXPIRED, DeclineReason::EXPIRED];
        yield 'cancelled by the clinic' => [SyncBookingRequestFromAppointment::CANCELLED, DeclineReason::CANCELLED];
    }

    #[DataProvider('provideSchedulingsDeclineReachesTheRequestOnceCases')]
    public function testSchedulingsDeclineReachesTheRequestOnce(string $outcome, DeclineReason $reason): void
    {
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment(self::APPOINTMENT, $outcome));
        // Delivered twice: nothing more
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment(self::APPOINTMENT, $outcome));

        self::assertSame($reason, $this->world->requests->requests[self::REQUEST]->declineReason());
        self::assertCount(1, $this->world->fakes->bookingDeclines);
    }

    public function testAConfirmationAtTheDeskConfirmsTheRequest(): void
    {
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment(self::APPOINTMENT, SyncBookingRequestFromAppointment::CONFIRMED, W::CLIENT));
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment(self::APPOINTMENT, SyncBookingRequestFromAppointment::DECLINED));

        $request = $this->world->requests->requests[self::REQUEST];
        self::assertSame([BookingRequestStatus::CONFIRMED, W::CLIENT], [$request->status(), $request->clientId()]);
        self::assertSame([], $this->world->fakes->bookingDeclines);
    }

    public function testAnAppointmentOfNoRequestChangesNothing(): void
    {
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment('01960000-0000-7000-8000-0000000a0fff', SyncBookingRequestFromAppointment::DECLINED));

        self::assertTrue($this->world->requests->requests[self::REQUEST]->isPending());
    }

    public function testTheAccountDeletionDeclinesWithoutMessageFreesTheSlotsAndErasesTheSnapshots(): void
    {
        $this->world->pending('01960000-0000-7000-8000-0000000d0002', W::CLINIC_2, appointmentId: '01960000-0000-7000-8000-0000000a0002')->confirm(W::CLIENT, W::utc(W::NOW));
        $erasure = new BookingRequestAccountErasure($this->world->requests, $this->world->booking, $this->world->clock);

        $erasure->erase(W::USER, new ErasedOwner(W::PHONE, null));
        // Idempotent
        $erasure->erase(W::USER, new ErasedOwner(null, null));

        self::assertSame([['withdraw', W::CLINIC, self::APPOINTMENT]], $this->world->booking->calls);
        $declined = $this->world->requests->requests[self::REQUEST];
        self::assertSame([DeclineReason::ACCOUNT_DELETED, null, null, null, null, null], [$declined->declineReason(), $declined->requesterFirstName(), $declined->requesterLastName(), $declined->requesterPhone(), $declined->newPetName(), $declined->newPetSpecies()]);
        self::assertSame(BookingRequestStatus::CONFIRMED, $this->world->requests->requests['01960000-0000-7000-8000-0000000d0002']->status());
        self::assertSame([W::USER, W::USER], $this->world->requests->scrubbed);

        // The late Scheduling event finds it resolved; an erased request is never told anything
        $this->world->syncHandler()(new SyncBookingRequestFromAppointment(self::APPOINTMENT, SyncBookingRequestFromAppointment::CANCELLED));
        $this->world->notice()->send($declined);
        self::assertSame([], $this->world->fakes->bookingDeclines);
    }

    public function testTheNoticeNeedsAPhoneAndANotifiedReason(): void
    {
        $request = $this->world->requests->requests[self::REQUEST];
        $this->world->notice()->send($request);
        $request->decline(DeclineReason::ACCOUNT_DELETED, W::utc(W::NOW));
        $this->world->notice()->send($request);

        self::assertSame([], $this->world->fakes->bookingDeclines);
        self::assertSame('dimanche 01/11 à 9h05', BookingDeclineNotice::slot(new \DateTimeImmutable('2026-11-01 09:05')));
    }
}
