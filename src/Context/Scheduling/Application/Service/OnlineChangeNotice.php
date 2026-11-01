<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Notification\AppointmentLabels;
use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PractitionerBellInterface;
use App\Context\Scheduling\Domain\Appointment;

/**
 * The bell of a client's cancel or move on the Portal (S4e): « RDV annulé en
 * ligne · Milo » / « RDV modifié en ligne · Milo » — « Sophie Dubois ·
 * jeudi 22/10 à 9h30 », to the old and the new practitioner, once each.
 */
final readonly class OnlineChangeNotice
{
    public function __construct(
        private ClinicTimezoneResolverInterface $timezones,
        private ClientContactInterface $contacts,
        private AnimalDirectoryInterface $animals,
        private PractitionerBellInterface $bell,
    ) {
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->announce($appointment, [], 'RDV annulé en ligne', 'online-booking-cancelled:' . $appointment->id()->toString());
    }

    /**
     * @param string $oldPractitionerUserId the practitioner before the move
     */
    public function moved(Appointment $appointment, string $oldPractitionerUserId): void
    {
        $key = \sprintf(
            'online-booking-changed:%s:%s:%s',
            $appointment->id()->toString(),
            $appointment->practitionerAssignee()->userId()->toString(),
            $appointment->timeSlot()->startsAtUtc()->getTimestamp(),
        );

        $this->announce($appointment, [$oldPractitionerUserId], 'RDV modifié en ligne', $key);
    }

    /**
     * @param list<string> $formerPractitioners
     */
    private function announce(Appointment $appointment, array $formerPractitioners, string $title, string $key): void
    {
        $clinicId = $appointment->clinicId()->toString();
        $startsAt = $appointment->timeSlot()->startsAtUtc()->setTimezone($this->timezones->resolveTimezone($appointment->clinicId()));
        $ownerId  = $appointment->ownerId();
        $animalId = $appointment->animalId();
        $animal   = null !== $animalId ? $this->animals->nameOf($clinicId, $animalId) : null;
        $owner    = null !== $ownerId ? $this->contacts->contactOf($clinicId, $ownerId)?->name : null;

        $this->bell->announceOnlineChange(
            clinicId: $clinicId,
            practitionerUserIds: array_values(array_unique([...$formerPractitioners, $appointment->practitionerAssignee()->userId()->toString()])),
            appointmentId: $appointment->id()->toString(),
            localDate: $startsAt->format('Y-m-d'),
            title: $title . (null !== $animal ? ' · ' . $animal : ''),
            detail: implode(' · ', array_filter([$owner, AppointmentLabels::slot($startsAt)])),
            idempotencyKey: $key,
        );
    }
}
