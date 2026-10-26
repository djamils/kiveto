<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PostNewAppointmentNotice;

use App\Context\Scheduling\Application\Notification\AppointmentLabels;
use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * « Nouveau RDV · Milo » — « Sophie Dubois · jeudi 22/10 à 9h30 · saisi par
 * Julie M. », to the appointment's veterinarian as it is when the worker runs,
 * unless they booked it themselves. An appointment cancelled in between is
 * not announced. One entry per appointment (idempotency key).
 */
#[AsMessageHandler]
final readonly class PostNewAppointmentNoticeHandler
{
    /** Idempotency key of the entry, also used to withdraw it. */
    public const string KEY = 'new-appointment:%s';

    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private ClinicDirectoryInterface $clinics,
        private ClientContactInterface $contacts,
        private AnimalDirectoryInterface $animals,
        private PractitionerBellInterface $bell,
    ) {
    }

    public function __invoke(PostNewAppointmentNotice $command): void
    {
        $appointment = $this->appointments->findById(AppointmentId::fromString($command->appointmentId));

        if (null === $appointment || AppointmentStatus::PLANNED !== $appointment->status()) {
            return;
        }

        $practitionerId = $appointment->practitionerAssignee()->userId()->toString();

        if ($command->actorUserId === $practitionerId) {
            return;
        }

        $clinicId = $appointment->clinicId()->toString();
        $startsAt = $appointment->timeSlot()->startsAtUtc()->setTimezone($this->clinics->clinic($clinicId)->timeZone);
        $ownerId  = $appointment->ownerId();
        $animalId = $appointment->animalId();
        $animal   = null !== $animalId ? $this->animals->nameOf($clinicId, $animalId) : null;
        $owner    = null !== $ownerId ? $this->contacts->contactOf($clinicId, $ownerId)?->name : null;
        $author   = null !== $command->actorUserId ? $this->clinics->printedName($clinicId, $command->actorUserId) : null;

        $this->bell->announceNewAppointment(
            clinicId: $clinicId,
            practitionerUserId: $practitionerId,
            appointmentId: $command->appointmentId,
            localDate: $startsAt->format('Y-m-d'),
            title: 'Nouveau RDV' . (null !== $animal ? ' · ' . $animal : ''),
            detail: implode(' · ', array_filter([
                $owner,
                AppointmentLabels::slot($startsAt),
                null !== $author ? 'saisi par ' . AppointmentLabels::shortName($author) : null,
            ])),
            idempotencyKey: \sprintf(self::KEY, $command->appointmentId),
        );
    }
}
