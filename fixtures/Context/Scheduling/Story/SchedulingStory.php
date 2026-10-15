<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment;
use App\Context\Scheduling\Application\Command\MarkAppointmentNoShow\MarkAppointmentNoShow;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Zenstruck\Foundry\Story;

final class SchedulingStory extends Story
{
    /** @var array<string, list<array{animalId: string, clientId: string}>> */
    private array $patientsByClinic = [];

    /** @var array<string, list<array{int, int}>> booked [start, end] timestamps by practitioner */
    private array $booked = [];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
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

            // The plan below fills today for both clinics whatever weekday it
            // is. When a week-plan slot lands on today, the two collide on the
            // same practitioner and the handler refuses the overlap — so today
            // belongs to $todayPlan alone.
            if ($localStart->format('Y-m-d') === $now->format('Y-m-d')) {
                continue;
            }

            $this->schedule($clinicId, $practitionerId, $localStart, $utcTz, $duration, $reason, $cancelled);
        }

        // A cancelled appointment, so the agenda and the board both show that
        // state. It is placed relative to the clock rather than at a fixed hour:
        // the domain refuses to cancel an appointment that has already passed,
        // and the fixtures are loaded at any time of day. Booked before the
        // day's plan: once cancelled it no longer holds the practitioner's
        // slot, whereas a planned one would refuse it.
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

        // Whatever day the fixtures are loaded on, both clinics need a booked
        // day: the daily board and the agenda open on today.
        $today = $now->setTime(0, 0, 0);

        // Paris has a busy day (a long « Fil du jour » on the dashboard, a
        // full agenda), Lyon a regular one; the calm day lives in
        // CalmDayClinicStory.
        $todayPlan = [
            // [hour, minute, clinicId, practitionerId, duration, reason, cancelled]
            [8,  30, $paris, $vetUserId,        30, 'Consultation vaccinale',    false],
            [9,  15, $paris, $contractorUserId, 20, 'Contrôle post-opératoire',  false],
            [9,  45, $paris, $vetUserId,        20, 'Rappel vaccinal',           false],
            [10, 0,  $paris, $contractorUserId, 30, 'Consultation dermatologie', false],
            [10, 15, $paris, $vetUserId,        30, 'Bilan gériatrique',         false],
            [10, 45, $paris, $contractorUserId, 20, 'Retrait de points',         false],
            [11, 0,  $paris, $vetUserId,        30, 'Bilan annuel',              false],
            [11, 45, $paris, $vetUserId,        20, 'Contrôle de poids',         false],
            [14, 0,  $paris, $vetUserId,        30, 'Primo-vaccination chaton',  false],
            [14, 30, $paris, $contractorUserId, 45, 'Détartrage',                false],
            [15, 0,  $paris, $vetUserId,        20, 'Otite — contrôle',          false],
            [15, 30, $paris, $contractorUserId, 30, 'Consultation boiterie',     false],
            [16, 0,  $paris, $vetUserId,        20, 'Vaccin',                    false],
            [16, 30, $paris, $vetUserId,        30, 'Bilan sanguin',             false],
            [17, 0,  $paris, $contractorUserId, 20, 'Vermifugation',             false],
            [17, 30, $paris, $vetUserId,        20, 'Identification puce',       false],
            [18, 15, $paris, $vetUserId,        30, 'Consultation comportement', false],
            [9,  0,  $lyon,  $vetUserId,        30, 'Consultation',              false],
            [10, 30, $lyon,  $lyonVetUserId,    60, 'Chirurgie de convenance',   false],
            [15, 0,  $lyon,  $lyonVetUserId,    20, 'Contrôle de poids',         false],
            [17, 0,  $lyon,  $vetUserId,        30, 'Consultation dermatologie', false],
        ];

        $this->buildLoadedWeek($paris, ['vet' => $vetUserId, 'dupont' => $contractorUserId], $monday, $now, $utcTz);

        foreach ($todayPlan as [$hour, $minute, $clinicId, $practitionerId, $duration, $reason, $cancelled]) {
            $localStart    = $today->setTime($hour, $minute, 0);
            $appointmentId = $this->schedule(
                $clinicId,
                $practitionerId,
                $localStart,
                $utcTz,
                $duration,
                $reason,
                $cancelled,
            );

            // What is already over when the fixtures load has been seen: the
            // timeline shows it as « terminé ».
            if ($localStart->modify(\sprintf('+%d minutes', $duration)) <= $now) {
                $this->commandBus->dispatch(new CompleteAppointment($appointmentId));
            }
        }
    }

    /**
     * Paris runs a loaded week (agenda-v-topbar-charge mock-up): every
     * bookable block of its trame (SchedulingPlanningBlockStory::PARIS_TRAME)
     * is filled at about 90 %, the on-call evenings about half. Today keeps
     * its own plan above. What is over is completed — one in twelve was a
     * no-show, one in ten is still to settle — and one in fourteen of what
     * is to come is cancelled.
     *
     * @param array<string, string> $staff
     */
    private function buildLoadedWeek(string $clinicId, array $staff, \DateTimeImmutable $monday, \DateTimeImmutable $now, \DateTimeZone $utcTz): void
    {
        $seed = 7;
        $rand = static function () use (&$seed): float {
            $seed = ($seed * 16807) % 2147483647;

            return $seed / 2147483647;
        };
        $pick = fn (array $list): string => $this->pickOne($list, $rand());

        $reasons = [
            'consult' => ['Consultation — boiterie', 'Consultation — vomissements', 'Consultation — prurit', 'Consultation — toux', 'Consultation — otite', 'Consultation — perte d\'appétit', 'Consultation — diarrhée', 'Consultation — plaie'],
            'vaccin'  => ['Vaccin — rappel CHPPiL', 'Vaccin — rappel TC', 'Primo-vaccination', 'Vaccin rage — voyage'],
            'suivi'   => ['Suivi — contrôle otite', 'Suivi — recontrôle post-op', 'Suivi IRC', 'Suivi — retrait de fils', 'Suivi — contrôle dermato'],
            'bilan'   => ['Bilan annuel', 'Bilan senior', 'Bilan cardiaque'],
            'chir'    => ['Chirurgie — stérilisation', 'Chirurgie — castration', 'Chirurgie — détartrage', 'Chirurgie — exérèse nodule'],
            'urg'     => ['Urgence — trauma', 'Urgence — intoxication', 'Urgence — dyspnée', 'Urgence — plaie profonde'],
        ];
        $durations = ['consult' => 30, 'vaccin' => 20, 'suivi' => 20, 'bilan' => 30, 'urg' => 30];

        $done   = 0;
        $toCome = 0;

        for ($day = 0; $day < 7; ++$day) {
            $date = $monday->modify(\sprintf('+%d days', $day));
            if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
                continue;
            }

            foreach (SchedulingPlanningBlockStory::PARIS_TRAME as [$weekday, $who, $start, $end, $type]) {
                if ($weekday !== (int) $date->format('N') || !\in_array($type, ['consultation', 'surgery', 'on_call'], true)) {
                    continue;
                }

                [$h, $m] = array_map('intval', explode(':', $start));
                $minute  = $h * 60 + $m;
                [$h, $m] = array_map('intval', explode(':', $end));
                $stop    = $h * 60 + $m;

                while ($minute < $stop) {
                    if ('on_call' === $type && $rand() < .55) {
                        $minute += 30;
                        continue;
                    }
                    if ('on_call' !== $type && $rand() < .1) {
                        $minute += 20;
                        continue;
                    }

                    $kind = match ($type) {
                        'surgery' => 'chir',
                        'on_call' => $rand() < .6 ? 'urg' : 'consult',
                        default   => $pick(['consult', 'consult', 'consult', 'vaccin', 'vaccin', 'suivi', 'suivi', 'bilan']),
                    };
                    $duration = 'chir' === $kind ? (int) $pick(['45', '60', '90']) : $durations[$kind];

                    if ($minute + $duration > $stop) {
                        break;
                    }

                    $localStart = $date->setTime(intdiv($minute, 60), $minute % 60);
                    $minute += $duration;
                    $isOver = $localStart->modify(\sprintf('+%d minutes', $duration)) <= $now;
                    $cancel = !$isOver && 5 === ++$toCome % 14;

                    // A booking of the regular week plan already holds the slot
                    if ($this->isBooked($staff[$who], $localStart, $duration)) {
                        continue;
                    }

                    $id = $this->schedule($clinicId, $staff[$who], $localStart, $utcTz, $duration, $pick($reasons[$kind]), $cancel);

                    // A few past bookings are left unsettled: the agenda offers « Marquer non venu »
                    if ($isOver && 3 !== ++$done % 10) {
                        $this->commandBus->dispatch(7 === $done % 12
                            ? new MarkAppointmentNoShow($id, $clinicId)
                            : new CompleteAppointment($id));
                    }
                }
            }
        }
    }

    /**
     * The clinic's patients, handed out in turn so every booking of the demo
     * carries a different animal.
     *
     * @return array{animalId?: string, clientId?: string}
     */
    private function nextPatient(string $clinicId): array
    {
        if (!isset($this->patientsByClinic[$clinicId])) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT BIN_TO_UUID(a.id) AS animal_id, BIN_TO_UUID(o.client_id) AS client_id
                 FROM animal__animals a
                 JOIN animal__ownerships o ON o.animal_id = a.id AND o.status = 'active' AND o.role = 'primary'
                 WHERE a.clinic_id = UUID_TO_BIN(:clinicId) AND a.status = 'ACTIVE'
                 ORDER BY a.name ASC",
                ['clinicId' => $clinicId],
            );

            $patients = [];

            foreach ($rows as $row) {
                $patients[] = [
                    'animalId' => RowAccessor::string($row, 'animal_id'),
                    'clientId' => RowAccessor::string($row, 'client_id'),
                ];
            }

            $this->patientsByClinic[$clinicId] = $patients;
        }

        $patients = $this->patientsByClinic[$clinicId];

        if ([] === $patients) {
            return [];
        }

        $patient                           = array_shift($patients);
        $patients[]                        = $patient;
        $this->patientsByClinic[$clinicId] = $patients;

        return $patient;
    }

    private function schedule(
        string $clinicId,
        string $practitionerId,
        \DateTimeImmutable $localStart,
        \DateTimeZone $utcTz,
        int $durationMinutes,
        string $reason,
        bool $cancelled,
    ): string {
        // A booking is made for an animal and its owner. An agenda of anonymous
        // slots shows nothing of what the day holds.
        $patient = $this->nextPatient($clinicId);

        $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
            clinicId: $clinicId,
            practitionerUserId: $practitionerId,
            startsAtUtc: $localStart->setTimezone($utcTz),
            durationMinutes: $durationMinutes,
            reason: $reason,
            notes: null,
            ownerId: $patient['clientId'] ?? null,
            animalId: $patient['animalId'] ?? null,
        ));

        \assert(\is_string($appointmentId));

        if (!$cancelled) {
            $this->booked[$practitionerId][] = [$localStart->getTimestamp(), $localStart->getTimestamp() + $durationMinutes * 60];
        }

        if ($cancelled) {
            $this->commandBus->dispatch(new CancelAppointment(
                appointmentId: $appointmentId,
                clinicId: $clinicId,
            ));
        }

        return $appointmentId;
    }

    private function isBooked(string $practitionerId, \DateTimeImmutable $start, int $durationMinutes): bool
    {
        $from = $start->getTimestamp();
        $to   = $from + $durationMinutes * 60;

        foreach ($this->booked[$practitionerId] ?? [] as [$s, $e]) {
            if ($from < $e && $to > $s) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $list
     */
    private function pickOne(array $list, float $random): string
    {
        $value = array_values($list)[(int) floor($random * \count($list))] ?? '';

        return \is_string($value) ? $value : '';
    }
}
