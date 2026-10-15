<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ListPrescriptionsForConsultation;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The side sheet of one consultation of the register (consultations-v-topbar
 * « Fiche »): its row, the synthesis (owner, motif, conclusion, weight), the
 * billed acts, the documents it produced and the follow-up it planned. A
 * reading view: every action leads to the consultation itself.
 */
final readonly class ConsultationRegisterSheetBuilder
{
    public function __construct(
        private ConsultationRegisterViewBuilder $register,
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return ?array<string, mixed> null when the consultation is not this clinic's
     */
    public function build(string $clinicId, string $consultationId, \DateTimeZone $tz): ?array
    {
        $row = $this->register->row($clinicId, $consultationId, $tz);

        if (null === $row) {
            return null;
        }

        $details = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));
        \assert($details instanceof ConsultationDetailsDTO);

        $weight = $details->vitals['weightKg'] ?? null;
        $animal = \is_array($row['a']) && \is_string($row['a']['n'] ?? null) ? $row['a']['n'] : '';

        return [
            'row'        => $row,
            'poids'      => null !== $weight ? str_replace('.', ',', rtrim(rtrim($weight, '0'), '.')) . ' kg' : null,
            'conclusion' => $this->conclusion($details),
            'actes'      => array_map(
                static fn (array $line): array => ['l' => $line['label'], 'q' => $line['quantity'], 'm' => $line['totalMinorUnits']],
                $details->billingLines,
            ),
            'ht'    => $details->totals['totalHtMinorUnits'],
            'tva'   => $details->totals['totalTvaMinorUnits'],
            'ttc'   => $details->totals['totalTtcMinorUnits'],
            'ordos' => $this->prescriptions($clinicId, $consultationId),
            'suivi' => $this->followUp($details, $tz),
            'urls'  => [
                'report' => $this->urls->generate('clinic_consultation_report_pdf', ['consultationId' => $consultationId]),
                'agenda' => $this->urls->generate('clinic_scheduling_agenda', ['new' => 1, 'patient' => $animal]),
            ],
        ];
    }

    /**
     * The closing summary, else the primary diagnosis, else none yet.
     */
    private function conclusion(ConsultationDetailsDTO $details): ?string
    {
        if (null !== $details->summary && '' !== trim($details->summary)) {
            return trim($details->summary);
        }

        $diagnoses = $details->diagnoses;
        usort($diagnoses, static fn (array $a, array $b): int => $b['isPrimary'] <=> $a['isPrimary']);

        return $diagnoses[0]['label'] ?? null;
    }

    /**
     * @return list<array{num: string, st: string, url: string}>
     */
    private function prescriptions(string $clinicId, string $consultationId): array
    {
        $rows = $this->queryBus->ask(new ListPrescriptionsForConsultation(clinicId: $clinicId, consultationId: $consultationId));
        $list = [];

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if (!$row instanceof ConsultationPrescriptionRow) {
                continue;
            }

            $list[] = [
                'num' => $row->number ?? 'Brouillon',
                'st'  => match ($row->status) {
                    'DRAFT'     => 'brouillon',
                    'CANCELLED' => 'annulée',
                    default     => 'émise',
                },
                'url' => $this->urls->generate('clinic_prescription_details', ['prescriptionId' => $row->id]),
            ];
        }

        return $list;
    }

    /**
     * The follow-up appointment the plan asks for, dated from the visit.
     *
     * @return ?array{t: string, d: string}
     */
    private function followUp(ConsultationDetailsDTO $details, \DateTimeZone $tz): ?array
    {
        foreach ($details->planActions as $action) {
            if ('FOLLOW_UP_APPOINTMENT' !== $action['kind'] || null === $action['followUpDays']) {
                continue;
            }

            $at = new \DateTimeImmutable($details->startedAtUtc, new \DateTimeZone('UTC'))
                ->setTimezone($tz)
                ->modify(\sprintf('+%d days', $action['followUpDays']))
            ;

            return ['t' => '' !== $action['description'] ? $action['description'] : 'Contrôle', 'd' => $at->format('Y-m-d')];
        }

        return null;
    }
}
