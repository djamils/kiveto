<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Turns a consultation into the JSON state the cockpit page renders from.
 *
 * The same shape is emitted by the initial hydration block and by every
 * mutation endpoint, so the client always re-renders from server truth instead
 * of patching its own copy.
 */
final readonly class CockpitStateBuilder
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CatalogItemProviderInterface $catalogItems,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFor(string $consultationId, string $clinicId): array
    {
        $details = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));

        if (!$details instanceof ConsultationDetailsDTO) {
            throw new \DomainException(\sprintf('Consultation "%s" not found.', $consultationId));
        }

        return $this->build($details);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ConsultationDetailsDTO $details): array
    {
        $animalLink    = $this->animalLink($details);
        $animalId      = $animalLink?->animalId;
        $medicalAlerts = null !== $animalId ? $this->medicalAlerts($animalId, $details->clinicId) : [];

        return [
            'consultationId'        => $details->consultationId,
            'clinicId'              => $details->clinicId,
            'patientId'             => $details->patientId,
            'animalId'              => $animalId,
            'status'                => $details->status,
            'isClosed'              => $details->isClosed(),
            'startedAtUtc'          => $details->startedAtUtc,
            'closedAtUtc'           => $details->closedAtUtc,
            'chiefComplaint'        => $details->chiefComplaint,
            'summary'               => $details->summary,
            'subjectiveText'        => $details->subjectiveText,
            'objectiveObservations' => $details->objectiveObservations,
            'teamMemo'              => $details->teamMemo,
            'vitals'                => $details->vitals,
            'motifs'                => $details->motifs,
            'typedVitals'           => $details->typedVitals,
            'examSystems'           => $details->examSystems,
            'diagnoses'             => $details->diagnoses,
            'planActions'           => $details->planActions,
            'prescriptionLines'     => $details->prescriptionLines,
            'billingLines'          => $details->billingLines,
            'totals'                => $details->totals,
            'medicalAlerts'         => $medicalAlerts,
            'allergyWarnings'       => $this->allergyWarnings($details, $medicalAlerts),
        ];
    }

    /**
     * Allergy labels of the animal behind a consultation, used by the catalogue
     * search to grey out incompatible medications.
     *
     * @return list<string>
     */
    public function allergyLabelsFor(string $patientId, string $clinicId): array
    {
        $link = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));

        if (!$link instanceof PatientAnimalLinkDto || null === $link->animalId) {
            return [];
        }

        $labels = [];

        foreach ($this->medicalAlerts($link->animalId, $clinicId) as $alert) {
            if ('ALLERGY' === $alert['kind']) {
                $labels[] = $alert['label'];
            }
        }

        return $labels;
    }

    private function animalLink(ConsultationDetailsDTO $details): ?PatientAnimalLinkDto
    {
        $link = $this->queryBus->ask(new GetPatientAnimalLink($details->patientId, $details->clinicId));

        return $link instanceof PatientAnimalLinkDto ? $link : null;
    }

    /**
     * @return list<array{id: string, kind: string, label: string, note: ?string}>
     */
    private function medicalAlerts(string $animalId, string $clinicId): array
    {
        $alerts = $this->queryBus->ask(new ListMedicalAlertsForAnimal($clinicId, $animalId));

        if (!\is_array($alerts)) {
            return [];
        }

        $views = [];

        foreach ($alerts as $alert) {
            if ($alert instanceof MedicalAlertView) {
                $views[] = [
                    'id'    => $alert->id,
                    'kind'  => $alert->kind,
                    'label' => $alert->label,
                    'note'  => $alert->note,
                ];
            }
        }

        return $views;
    }

    /**
     * Cross-checks each prescribed article's active substances against the
     * animal's allergy alerts. Label-based and deliberately naive — the copy
     * asks the practitioner to verify rather than claiming a hard incompatibility.
     *
     * @param list<array{id: string, kind: string, label: string, note: ?string}> $medicalAlerts
     *
     * @return list<array{prescriptionLineId: string, medication: string, alertLabel: string, substance: string}>
     */
    private function allergyWarnings(ConsultationDetailsDTO $details, array $medicalAlerts): array
    {
        $allergies = array_values(array_filter(
            $medicalAlerts,
            static fn (array $alert): bool => 'ALLERGY' === $alert['kind'],
        ));

        if ([] === $allergies || [] === $details->prescriptionLines) {
            return [];
        }

        $warnings = [];

        /** @var array<string, list<string>> $substancesByArticle */
        $substancesByArticle = [];

        foreach ($details->prescriptionLines as $line) {
            $articleId = $line['articleId'];

            if (null === $articleId) {
                continue;
            }

            if (!\array_key_exists($articleId, $substancesByArticle)) {
                $substancesByArticle[$articleId] = $this->catalogItems->activeSubstances(
                    $articleId,
                    $details->clinicId,
                );
            }

            foreach ($substancesByArticle[$articleId] as $substance) {
                foreach ($allergies as $allergy) {
                    if (!str_contains(mb_strtolower($substance), mb_strtolower($allergy['label']))) {
                        continue;
                    }

                    $warnings[] = [
                        'prescriptionLineId' => $line['id'],
                        'medication'         => $line['label'],
                        'alertLabel'         => $allergy['label'],
                        'substance'          => $substance,
                    ];
                }
            }
        }

        return $warnings;
    }
}
