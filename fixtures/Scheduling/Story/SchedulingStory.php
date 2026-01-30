<?php

declare(strict_types=1);

namespace App\Fixtures\Scheduling\Story;

use App\Fixtures\Scheduling\AppointmentFactory;
use App\Fixtures\Scheduling\WaitingRoomEntryFactory;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use Zenstruck\Foundry\Story;

final class SchedulingStory extends Story
{
    public function __construct(
        private readonly AppointmentFactory $appointmentFactory,
        private readonly WaitingRoomEntryFactory $waitingRoomEntryFactory,
    ) {
    }

    public function build(): void
    {
        // Note: This story assumes clinics, users, owners, and animals exist from other BC fixtures
        // Typical usage: load AccessControl, Clinic, Client, and Animal fixtures first

        // Example clinic/user/owner/animal IDs (would come from other stories in reality)
        // For now, this is a template - actual IDs should be injected or retrieved

        // Uncomment and adjust when integrating with other BC fixtures:
        /*
        $clinicId = '...'; // from Clinic BC
        $vetUserId = '...'; // from IdentityAccess + AccessControl BC
        $ownerId = '...'; // from Client BC
        $animalId = '...'; // from Animal BC

        // Create some appointments
        $appointment1 = $this->appointmentFactory->create(
            clinicId: $clinicId,
            ownerId: $ownerId,
            animalId: $animalId,
            practitionerUserId: $vetUserId,
            startsAtUtc: new \DateTimeImmutable('+1 day 09:00:00'),
            durationMinutes: 30,
            reason: 'Vaccination',
            notes: 'Annual checkup',
        );

        $appointment2 = $this->appointmentFactory->create(
            clinicId: $clinicId,
            ownerId: $ownerId,
            animalId: $animalId,
            practitionerUserId: $vetUserId,
            startsAtUtc: new \DateTimeImmutable('+1 day 10:00:00'),
            durationMinutes: 45,
            reason: 'Surgery consultation',
        );

        // Create a waiting room entry for first appointment
        $this->waitingRoomEntryFactory->createFromAppointment(
            clinicId: $clinicId,
            appointmentId: $appointment1->id()->toString(),
            ownerId: $ownerId,
            animalId: $animalId,
            arrivalMode: WaitingRoomArrivalMode::STANDARD,
            priority: 0,
        );

        // Create an emergency walk-in
        $this->waitingRoomEntryFactory->createWalkIn(
            clinicId: $clinicId,
            ownerId: null,
            animalId: null,
            foundAnimalDescription: 'Injured stray cat',
            arrivalMode: WaitingRoomArrivalMode::EMERGENCY,
            priority: 10,
            triageNotes: 'Bleeding, requires immediate attention',
        );
        */
    }
}
