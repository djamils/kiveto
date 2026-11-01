<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Command\DeletePortalAccount\AccountErasureStepInterface;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Slice 7's step of the account deletion (AC 90, 269): the pending booking
 * requests are declined (`account_deleted`, no message), their slots freed
 * in Scheduling, then the requester and new-pet snapshots of every request
 * of the account are erased. Idempotent: a retry finds nothing pending and
 * nothing left to erase.
 */
final readonly class BookingRequestAccountErasure implements AccountErasureStepInterface
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private OnlineBookingInterface $booking,
        private ClockInterface $clock,
    ) {
    }

    public function erase(string $userId, ErasedOwner $owner): void
    {
        $now = $this->clock->now();

        foreach ($this->requests->findPendingByUserForUpdate($userId) as $request) {
            // Declined first: Scheduling's cancellation event then finds it resolved and sends nothing
            $request->decline(DeclineReason::ACCOUNT_DELETED, $now);
            $this->requests->save($request);

            $this->booking->withdrawPending($request->clinicId(), $request->appointmentId());
        }

        $this->requests->scrubUser($userId);
    }
}
