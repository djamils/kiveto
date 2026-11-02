<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PostPendingBookingNotice;

use App\Context\Scheduling\Application\Notification\AppointmentLabels;
use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * « RDV en ligne à confirmer · Milo » — « Sophie Dubois · jeudi 22/10 à
 * 9h30 », to the appointment's practitioner as it is when the worker runs.
 * A request confirmed, declined or withdrawn in between is not announced.
 * One entry per appointment (idempotency key), withdrawn once it is handled;
 * moved to another practitioner, the unread entry of the former one is
 * withdrawn before the new one is posted.
 */
#[AsMessageHandler]
final readonly class PostPendingBookingNoticeHandler
{
    /** Idempotency key of the entry, also used to withdraw it. */
    public const string KEY = 'online-booking-pending:%s';

    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private ClinicDirectoryInterface $clinics,
        private ClientContactInterface $contacts,
        private AnimalDirectoryInterface $animals,
        private PractitionerBellInterface $bell,
    ) {
    }

    public function __invoke(PostPendingBookingNotice $command): void
    {
        $appointment = $this->appointments->findById(AppointmentId::fromString($command->appointmentId));

        if (null === $appointment || !$appointment->status()->isPending()) {
            return;
        }

        $key = \sprintf(self::KEY, $command->appointmentId);

        if ($command->replace) {
            $this->bell->withdraw($key);
        }

        $clinicId = $appointment->clinicId()->toString();
        $startsAt = $appointment->timeSlot()->startsAtUtc()->setTimezone($this->clinics->clinic($clinicId)->timeZone);
        $ownerId  = $appointment->ownerId();
        $animalId = $appointment->animalId();
        $animal   = null !== $animalId ? $this->animals->nameOf($clinicId, $animalId) : null;
        $owner    = null !== $ownerId ? $this->contacts->contactOf($clinicId, $ownerId)?->name : null;

        $this->bell->announcePendingBooking(
            clinicId: $clinicId,
            practitionerUserId: $appointment->practitionerAssignee()->userId()->toString(),
            appointmentId: $command->appointmentId,
            localDate: $startsAt->format('Y-m-d'),
            title: 'RDV en ligne à confirmer' . (null !== $animal ? ' · ' . $animal : ''),
            detail: implode(' · ', array_filter([$owner, AppointmentLabels::slot($startsAt)])),
            idempotencyKey: $key,
        );
    }
}
