<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

// Depends on SchedulingStory (appointment refs must exist).
// Uses Foundry EntityFactory to bypass domain temporal invariants (arrivedAtUtc, status).
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryOrigin;
use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use Zenstruck\Foundry\Story;

final class WaitingRoomStory extends Story
{
    public function build(): void
    {
        $clinicId = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Walk-in, STANDARD, WAITING — convulsions, arrived 2h ago
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::WALK_IN)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create([
                'foundAnimalDescription' => 'Gaia (Canin Labrador) — Mme Fauchard',
                'triageNotes'            => 'Convulsions',
                'priority'               => 0,
                'arrivedAtUtc'           => $now->modify('-2 hours'),
            ])
        ;

        // 2. Walk-in, EMERGENCY, WAITING — trauma, arrived 30 min ago
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::WALK_IN)
            ->withArrivalMode(WaitingRoomArrivalMode::EMERGENCY)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create([
                'foundAnimalDescription' => 'Chat inconnu — M. Arnault',
                'triageNotes'            => 'Trauma choc voiture',
                'priority'               => 2,
                'arrivedAtUtc'           => $now->modify('-30 minutes'),
            ])
        ;

        // 3. Walk-in, STANDARD, WAITING — no notes, arrived 45 min ago
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::WALK_IN)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create([
                'foundAnimalDescription' => 'Nala (Félin) — Mme Moreau',
                'priority'               => 0,
                'arrivedAtUtc'           => $now->modify('-45 minutes'),
            ])
        ;

        // 4. From-appointment, STANDARD, WAITING — real owner/animal, arrived 1h ago
        $appt1 = SchedulingStory::get('appointment:paris:day0:09h00');
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::SCHEDULED)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->withLinkedAppointmentId(\is_string($appt1) ? $appt1 : null)
            ->withOwnerId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->withAnimalId(AnimalDataStory::ANIMAL_REX_ID)
            ->create([
                'priority'     => 0,
                'arrivedAtUtc' => $now->modify('-1 hour'),
            ])
        ;

        // 5. From-appointment, STANDARD, IN_SERVICE — arrived 90min, service started 20min ago
        $appt2 = SchedulingStory::get('appointment:paris:day1:10h00');
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::SCHEDULED)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::IN_SERVICE)
            ->withLinkedAppointmentId(\is_string($appt2) ? $appt2 : null)
            ->withOwnerId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->withAnimalId(AnimalDataStory::ANIMAL_REX_ID)
            ->create([
                'priority'            => 0,
                'arrivedAtUtc'        => $now->modify('-90 minutes'),
                'serviceStartedAtUtc' => $now->modify('-20 minutes'),
            ])
        ;

        // 6. From-appointment, STANDARD, IN_SERVICE — arrived 60min, service started 5min ago
        $appt3 = SchedulingStory::get('appointment:paris:day3:08h30');
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::SCHEDULED)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::IN_SERVICE)
            ->withLinkedAppointmentId(\is_string($appt3) ? $appt3 : null)
            ->withOwnerId(ClientDataStory::CLIENT_SOPHIE_ID)
            ->withAnimalId(AnimalDataStory::ANIMAL_REX_ID)
            ->create([
                'priority'            => 0,
                'arrivedAtUtc'        => $now->modify('-60 minutes'),
                'serviceStartedAtUtc' => $now->modify('-5 minutes'),
            ])
        ;

        // 7. Walk-in, STANDARD, WAITING — annual vaccine, arrived 75min ago
        WaitingRoomEntryEntityFactory::new()
            ->withClinicId($clinicId)
            ->withOrigin(WaitingRoomEntryOrigin::WALK_IN)
            ->withArrivalMode(WaitingRoomArrivalMode::STANDARD)
            ->withStatus(WaitingRoomEntryStatus::WAITING)
            ->create([
                'foundAnimalDescription' => 'Milo (Canin British)',
                'triageNotes'            => 'Vaccin annuel',
                'priority'               => 0,
                'arrivedAtUtc'           => $now->modify('-75 minutes'),
            ])
        ;
    }
}
