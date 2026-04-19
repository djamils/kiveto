<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Zenstruck\Foundry\Story;

final class SchedulingStory extends Story
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function build(): void
    {
        $vetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet@kiveto.local']);
        if (null === $vetUser) {
            throw new \RuntimeException('vet@kiveto.local not found. ClinicVetStory must be loaded first.');
        }
        $contractorUser = ClinicUserFactory::repository()->findOneBy(['email' => 'contractor@kiveto.local']);
        if (null === $contractorUser) {
            throw new \RuntimeException(
                'contractor@kiveto.local not found. ClinicMembershipDataStory must be loaded first.',
            );
        }

        $vetUserId        = $vetUser->getId()->toRfc4122();
        $contractorUserId = $contractorUser->getId()->toRfc4122();

        $paris = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $lyon  = ClinicDataStory::GROUP_CLINIC_ID;

        $parisTz = new \DateTimeZone('Europe/Paris');
        $utcTz   = new \DateTimeZone('UTC');

        $now    = $this->clock->now()->setTimezone($parisTz);
        $monday = $now->modify('monday this week')->setTime(0, 0, 0);

        // If "this week" monday is already 6+ days behind us (i.e. today is Sunday),
        // shift the anchor to next week so all fixture appointments remain in the future.
        if ($monday->diff($now)->days >= 6) {
            $monday = $monday->modify('+7 days');
        }

        $plan = [
            // [dayOffset, hour, minute, clinicId, practitionerId, duration, reason, cancelled]
            [0, 9,  0,  $paris, $vetUserId,        30, 'Consultation',                      false],
            [1, 10, 0,  $paris, $contractorUserId, 20, 'Vaccin',                            false],
            [2, 14, 0,  $lyon,  $vetUserId,        60, 'Chirurgie Stérilisation',           false],
            [3, 8,  30, $paris, $vetUserId,        30, 'Bilan annuel',                      false],
            [3, 11, 0,  $paris, $contractorUserId, 20, 'Vaccin',                            false],
            [4, 11, 0,  $lyon,  $vetUserId,        15, 'Suivi post-op J+7',                 false],
            [5, 10, 0,  $paris, $vetUserId,        30, "Vaccination d'Artagnan",            true],
            [6, 15, 0,  $lyon,  $vetUserId,        20, 'Contrôle poids',                    false],
        ];

        // Paris clinic has a seeded owner+animal (Rex owned by Sophie);
        // Lyon has Felix owned by Émilie. Stick with clinic-owned patients so
        // the OwnerExistenceChecker validates in both clinics.
        $patientByClinic = [
            $paris => [
                'owner'  => ClientDataStory::CLIENT_SOPHIE_ID,
                'animal' => AnimalDataStory::ANIMAL_REX_ID,
            ],
            $lyon => [
                'owner'  => ClientDataStory::CLIENT_EMILIE_ID,
                'animal' => null,
            ],
        ];

        foreach ($plan as [$dayOffset, $hour, $minute, $clinicId, $practitionerId, $duration, $reason, $cancelled]) {
            $localStart = $monday
                ->modify(\sprintf('+%d days', $dayOffset))
                ->setTime($hour, $minute, 0)
            ;

            $patient = $patientByClinic[$clinicId];

            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: $clinicId,
                ownerId: $patient['owner'],
                animalId: $patient['animal'],
                practitionerUserId: $practitionerId,
                startsAtUtc: $localStart->setTimezone($utcTz),
                durationMinutes: $duration,
                reason: $reason,
                notes: null,
            ));

            if ($cancelled) {
                \assert(\is_string($appointmentId));
                $this->commandBus->dispatch(new CancelAppointment(
                    appointmentId: $appointmentId,
                ));
            }
        }
    }
}
