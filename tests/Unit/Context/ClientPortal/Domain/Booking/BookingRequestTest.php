<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain\Booking;

use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Tests\Unit\Context\ClientPortal\Support\BookingRequestWorld as W;
use PHPUnit\Framework\TestCase;

final class BookingRequestTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000d0001';

    public function testARequestIsPendingWithItsSnapshots(): void
    {
        $request = new W()->pending(self::ID);

        self::assertTrue($request->isPending());
        self::assertSame(
            [self::ID, W::USER, W::CLINIC, W::LISTING, self::ID, 'Première consultation', '2026-11-05T13:30:00+00:00', 'Pixel', 'cat', 'Sophie', 'Martin', W::PHONE, BookingRequestStatus::PENDING, null, null, '2026-11-01T09:00:00+00:00', null],
            [$request->id()->toString(), $request->userId(), $request->clinicId(), $request->listingId(), $request->appointmentId(), $request->motifLabel(), $request->startAt()->format('c'), $request->newPetName(), $request->newPetSpecies(), $request->requesterFirstName(), $request->requesterLastName(), $request->requesterPhone(), $request->status(), $request->declineReason(), $request->clientId(), $request->createdAt()->format('c'), $request->resolvedAt()],
        );
    }

    public function testAConfirmationKeepsTheAttachedFileWhenNoneIsGiven(): void
    {
        $request = new W()->pending(self::ID);
        $request->attachClient(W::CLIENT);

        self::assertTrue($request->confirm(null, W::utc(W::NOW)));
        self::assertSame([BookingRequestStatus::CONFIRMED, W::CLIENT, '2026-11-02T10:00:00+00:00'], [$request->status(), $request->clientId(), $request->resolvedAt()?->format('c')]);

        // Resolved: nothing moves any more
        self::assertFalse($request->confirm(W::NEW_FILE, W::utc(W::NOW)));
        self::assertFalse($request->decline(DeclineReason::EXPIRED, W::utc(W::NOW)));
        $request->attachClient(W::NEW_FILE);
        self::assertSame([BookingRequestStatus::CONFIRMED, W::CLIENT, null], [$request->status(), $request->clientId(), $request->declineReason()]);
    }

    public function testADeclineRecordsItsReason(): void
    {
        $request = new W()->pending(self::ID);

        self::assertTrue($request->decline(DeclineReason::CANCELLED, W::utc(W::NOW)));
        self::assertSame([BookingRequestStatus::DECLINED, DeclineReason::CANCELLED], [$request->status(), $request->declineReason()]);
        self::assertSame([true, true, true, false], array_map(static fn (DeclineReason $r): bool => $r->isNotified(), DeclineReason::cases()));
    }
}
