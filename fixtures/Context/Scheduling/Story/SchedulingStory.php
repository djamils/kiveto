<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
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

        $lyonVetUser = ClinicUserFactory::repository()->findOneBy(['email' => 'vet.lyon@kiveto.local']);
        if (null === $lyonVetUser) {
            throw new \RuntimeException(
                'vet.lyon@kiveto.local not found. ClinicStaffProfileDataStory must be loaded first.',
            );
        }

        $vetUserId        = $vetUser->getId()->toRfc4122();
        $contractorUserId = $contractorUser->getId()->toRfc4122();
        $lyonVetUserId    = $lyonVetUser->getId()->toRfc4122();

        $paris = ClinicDataStory::INDEPENDENT_CLINIC_ID;
        $lyon  = ClinicDataStory::GROUP_CLINIC_ID;

        $parisTz = new \DateTimeZone('Europe/Paris');
        $utcTz   = new \DateTimeZone('UTC');

        $now    = $this->clock->now()->setTimezone($parisTz);
        $monday = $now->modify('monday this week')->setTime(0, 0, 0);

        // If "this week" monday is already 5+ days behind us (i.e. today is Saturday or Sunday),
        // shift the anchor to next week so all fixture appointments remain in the future.
        if ($monday->diff($now)->days >= 5) {
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
            [5, 10, 0,  $paris, $vetUserId,        30, "Vaccination d'Artagnan",            false],
            [6, 15, 0,  $lyon,  $vetUserId,        20, 'Contrôle poids',                    false],
        ];

        foreach ($plan as [$dayOffset, $hour, $minute, $clinicId, $practitionerId, $duration, $reason, $cancelled]) {
            $localStart = $monday
                ->modify(\sprintf('+%d days', $dayOffset))
                ->setTime($hour, $minute, 0)
            ;

            $this->schedule($clinicId, $practitionerId, $localStart, $utcTz, $duration, $reason, $cancelled);
        }

        // Whatever day the fixtures are loaded on, both clinics need a booked
        // day: the daily board and the agenda open on today.
        $today = $now->setTime(0, 0, 0);

        $todayPlan = [
            // [hour, minute, clinicId, practitionerId, duration, reason, cancelled]
            [8,  30, $paris, $vetUserId,        30, 'Consultation vaccinale',    false],
            [9,  15, $paris, $contractorUserId, 20, 'Contrôle post-opératoire',  false],
            [11, 0,  $paris, $vetUserId,        30, 'Bilan annuel',              false],
            [14, 30, $paris, $contractorUserId, 45, 'Détartrage',                false],
            [16, 0,  $paris, $vetUserId,        20, 'Vaccin',                    false],
            [9,  0,  $lyon,  $vetUserId,        30, 'Consultation',              false],
            [10, 30, $lyon,  $lyonVetUserId,    60, 'Chirurgie de convenance',   false],
            [15, 0,  $lyon,  $lyonVetUserId,    20, 'Contrôle de poids',         false],
            [17, 0,  $lyon,  $vetUserId,        30, 'Consultation dermatologie', false],
        ];

        foreach ($todayPlan as [$hour, $minute, $clinicId, $practitionerId, $duration, $reason, $cancelled]) {
            $this->schedule(
                $clinicId,
                $practitionerId,
                $today->setTime($hour, $minute, 0),
                $utcTz,
                $duration,
                $reason,
                $cancelled,
            );
        }

        // A cancelled appointment, so the agenda and the board both show that
        // state. It is placed relative to the clock rather than at a fixed hour:
        // the domain refuses to cancel an appointment that has already passed,
        // and the fixtures are loaded at any time of day.
        $this->schedule(
            $paris,
            $vetUserId,
            $now->modify('+90 minutes'),
            $utcTz,
            20,
            "Vaccination d'Artagnan (annulé)",
            true,
        );

        $this->schedule(
            $lyon,
            $lyonVetUserId,
            $now->modify('+3 hours'),
            $utcTz,
            30,
            'Contrôle annulé par le propriétaire',
            true,
        );
    }

    private function schedule(
        string $clinicId,
        string $practitionerId,
        \DateTimeImmutable $localStart,
        \DateTimeZone $utcTz,
        int $durationMinutes,
        string $reason,
        bool $cancelled,
    ): void {
        $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
            clinicId: $clinicId,
            practitionerUserId: $practitionerId,
            startsAtUtc: $localStart->setTimezone($utcTz),
            durationMinutes: $durationMinutes,
            reason: $reason,
            notes: null,
        ));

        \assert(\is_string($appointmentId));

        if ($cancelled) {
            $this->commandBus->dispatch(new CancelAppointment(
                appointmentId: $appointmentId,
            ));
        }
    }
}
