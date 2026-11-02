<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeclineBookingRequest;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Service\BookingDeclineNotice;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * One transaction: the request read `FOR UPDATE` and still pending, the
 * appointment declined in Scheduling (the slot is freed; its refusals pass
 * through), the request `declined` (`declined`) by the staff member, then
 * `PORTAL_BOOKING_DECLINED` to the requester's phone. Scheduling's
 * integration event later finds the request resolved and sends nothing.
 */
#[AsMessageHandler]
final readonly class DeclineBookingRequestHandler
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private OnlineBookingInterface $booking,
        private BookingDeclineNotice $notice,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws BookingRequestNotFoundException
     * @throws BookingRequestAlreadyResolvedException
     */
    public function __invoke(DeclineBookingRequest $command): void
    {
        $request = $this->requests->findInClinicForUpdate($command->clinicId, $command->requestId)
            ?? throw new BookingRequestNotFoundException($command->requestId);

        if (!$request->isPending()) {
            throw new BookingRequestAlreadyResolvedException($command->requestId);
        }

        $this->booking->decline($request->clinicId(), $request->appointmentId());

        $request->decline(DeclineReason::DECLINED, $this->clock->now(), $command->actorUserId, $command->actorName);
        $this->requests->save($request);

        $this->notice->send($request);
    }
}
