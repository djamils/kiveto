<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment;

use App\Context\ClientPortal\Application\Service\BookingDeclineNotice;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Booking\DeclineReason;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Idempotent: an appointment that is no new client's request, or a request
 * already resolved (by « Demandes Portal », the account deletion or an
 * earlier delivery of the same event), is left untouched. Confirmed →
 * `confirmed` (the client file if known); declined, expired or cancelled →
 * `declined` with that reason and `PORTAL_BOOKING_DECLINED` once.
 */
#[AsMessageHandler]
final readonly class SyncBookingRequestFromAppointmentHandler
{
    public function __construct(
        private BookingRequestRepositoryInterface $requests,
        private BookingDeclineNotice $notice,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SyncBookingRequestFromAppointment $command): void
    {
        $request = $this->requests->findByAppointmentForUpdate($command->appointmentId);

        if (null === $request || !$request->isPending()) {
            return;
        }

        $now = $this->clock->now();

        if (SyncBookingRequestFromAppointment::CONFIRMED === $command->outcome) {
            $request->confirm($command->ownerId, $now);
            $this->requests->save($request);

            return;
        }

        $request->decline(match ($command->outcome) {
            SyncBookingRequestFromAppointment::EXPIRED   => DeclineReason::EXPIRED,
            SyncBookingRequestFromAppointment::CANCELLED => DeclineReason::CANCELLED,
            default                                      => DeclineReason::DECLINED,
        }, $now);
        $this->requests->save($request);

        $this->notice->send($request);
    }
}
