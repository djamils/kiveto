<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\BookingDeclinedMessage;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;

/**
 * `PORTAL_BOOKING_DECLINED` (S7d): a new client whose request was declined,
 * expired or cancelled by the clinic is told by SMS on the phone the
 * request was made with. Once per request (key
 * `portal-booking-declined:<requestId>`); never for a deleted account.
 */
final readonly class BookingDeclineNotice
{
    private const array DAYS = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public function __construct(
        private NotificationRequesterInterface $notifications,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /** To be called last in the transaction that declined the request. */
    public function send(BookingRequest $request): void
    {
        $phone  = $request->requesterPhone();
        $reason = $request->declineReason();

        if (null === $phone || null === $reason || !$reason->isNotified()) {
            return;
        }

        $clinic = $this->clinics->clinicOf($request->clinicId());

        $this->notifications->requestBookingDeclined(new BookingDeclinedMessage(
            requestId: $request->id()->toString(),
            phone: $phone,
            recipientName: trim($request->requesterFirstName() . ' ' . $request->requesterLastName()),
            clinicName: $clinic->name,
            slotLabel: self::slot($request->startAt()->setTimezone(new \DateTimeZone($clinic->timeZone))),
        ));
    }

    /** « jeudi 22/10 à 9h30 », as the appointment SMS print a slot. */
    public static function slot(\DateTimeImmutable $at): string
    {
        return self::DAYS[(int) $at->format('N')] . ' ' . $at->format('d/m') . ' à ' . $at->format('G') . 'h' . $at->format('i');
    }
}
