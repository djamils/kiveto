<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Admission\Application\Query\ListActiveAdmissions\ListActiveAdmissions;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Bus\QueryInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * What the cockpit shows around the record itself and that does not change
 * while it is being filled: where the consultation comes from (appointment,
 * walk-in, emergency), the clinic letterhead of the paper previews, the
 * preventive acts the animal's file says are due, the practitioners.
 */
final readonly class CockpitContextBuilder
{
    private const array MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /** A preventive act is offered when it falls due within this many days. */
    private const int DUE_WINDOW_DAYS = 30;

    public function __construct(
        private QueryBusInterface $queryBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{type: string, at: ?string, practitioner: ?string}
     */
    public function origin(ConsultationDetailsDTO $details, string $timeZone): array
    {
        $tz = new \DateTimeZone($timeZone);

        if (null !== $details->appointmentId) {
            $appointment = $this->ask(new GetAppointmentDetails($details->appointmentId));

            if ($appointment instanceof AppointmentDetails) {
                return [
                    'type'         => 'rdv',
                    'at'           => new \DateTimeImmutable($appointment->startsAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz)->format('H:i'),
                    'practitioner' => null,
                ];
            }
        }

        $admissions = $this->ask(new ListActiveAdmissions($details->clinicId));

        foreach (\is_array($admissions) ? $admissions : [] as $admission) {
            if (!$admission instanceof WaitingRoomItemDto || $admission->admissionId !== $details->admissionId) {
                continue;
            }

            $openedAt  = new \DateTimeImmutable($admission->openedAt, new \DateTimeZone('UTC'))->setTimezone($tz);
            $startedAt = new \DateTimeImmutable($details->startedAtUtc, new \DateTimeZone('UTC'))->setTimezone($tz);

            // An older consultation of the same animal says nothing about today's arrival.
            if ($openedAt->format('Y-m-d') !== $startedAt->format('Y-m-d')) {
                break;
            }

            $emergency = 'emergency' === $admission->intakeChannel || \in_array($admission->triageLevel, ['vital_emergency', 'emergency'], true);

            return [
                'type'         => $emergency ? 'urgence' : 'sans-rdv',
                'at'           => $openedAt->format('H:i'),
                'practitioner' => null,
            ];
        }

        return ['type' => 'sans-rdv', 'at' => null, 'practitioner' => null];
    }

    /**
     * Clinic identity printed at the head of the ordonnance, invoice and report
     * previews.
     *
     * @return array{name: string, lines: list<string>, contact: ?string, siret: ?string, ordinal: ?string, city: ?string, timeZone: string}
     */
    public function letterhead(string $clinicId): array
    {
        $clinic = $this->ask(new GetClinic($clinicId));

        if (!$clinic instanceof ClinicDto) {
            return ['name' => 'Clinique vétérinaire', 'lines' => [], 'contact' => null, 'siret' => null, 'ordinal' => null, 'city' => null, 'timeZone' => 'Europe/Paris'];
        }

        $letterhead = $clinic->letterhead;
        $contact    = null !== $letterhead
            ? implode(' · ', array_filter([$letterhead->phone, $letterhead->email]))
            : '';

        return [
            'name'     => $letterhead->legalName ?? $clinic->name,
            'lines'    => $letterhead->addressLines ?? [],
            'contact'  => '' !== $contact ? $contact : null,
            'siret'    => $letterhead?->formattedSiret(),
            'ordinal'  => $letterhead?->ordinalNumber,
            'city'     => $letterhead?->city,
            'timeZone' => $clinic->timeZone,
        ];
    }

    /**
     * Latest act of each preventive protocol whose next due date is past or
     * close: « Dû selon le dossier » proposes to add it to the plan.
     *
     * @return list<array{id: string, label: string, why: string}>
     */
    public function due(?string $animalId, string $clinicId): array
    {
        if (null === $animalId) {
            return [];
        }

        $records = $this->ask(new ListAnimalPreventiveCare($clinicId, $animalId));
        $today   = $this->clock->now()->setTime(0, 0);
        $limit   = $today->modify(\sprintf('+%d days', self::DUE_WINDOW_DAYS));
        $seen    = [];
        $due     = [];

        // Records come newest first: the first one of a protocol is its latest act.
        foreach (\is_array($records) ? $records : [] as $record) {
            if (!$record instanceof PreventiveCareRecordView) {
                continue;
            }

            $key = $record->kind . '|' . mb_strtolower($record->label);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (null === $record->nextDueOn || $record->nextDueOn > $limit) {
                continue;
            }

            $date  = (int) $record->nextDueOn->format('j') . ' ' . self::MONTHS[(int) $record->nextDueOn->format('n') - 1];
            $due[] = [
                'id'    => $record->id,
                'label' => $record->label,
                'why'   => $record->nextDueOn < $today ? 'rappel dû depuis le ' . $date : 'rappel prévu le ' . $date,
            ];
        }

        return $due;
    }

    private function ask(QueryInterface $query): mixed
    {
        try {
            return $this->queryBus->ask($query);
        } catch (\Throwable) {
            return null;
        }
    }
}
