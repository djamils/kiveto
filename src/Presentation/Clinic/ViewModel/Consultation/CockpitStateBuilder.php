<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\ListStockItemsByClinic;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
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
        $team          = $this->team($details->clinicId);

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
            'prescriptionLines'     => $this->prescriptionLines($details),
            'billingLines'          => $details->billingLines,
            'totals'                => $details->totals,
            'medicalAlerts'         => $medicalAlerts,
            'allergyWarnings'       => $this->allergyWarnings($details, $medicalAlerts),
            'teamNotes'             => $this->notes($details, $team, false),
            'addenda'               => $this->notes($details, $team, true),
        ];
    }

    /**
     * Name, initials and agenda colour of each practitioner of the clinic.
     *
     * @return array<string, array{name: string, initials: string, color: string}>
     */
    public function team(string $clinicId): array
    {
        $members = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        $team    = [];

        foreach (\is_array($members) ? $members : [] as $member) {
            if (!$member instanceof ClinicVeterinarianItem) {
                continue;
            }

            $name = $member->displayName ?? 'Praticien';

            $team[$member->userId] = [
                'name'     => $name,
                'initials' => self::initials($name),
                'color'    => $member->agendaColor ?? '#4f86c6',
            ];
        }

        return $team;
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

    /**
     * Prescription lines with the kind of their article — a drug goes under
     * « Médicaments » of the plan, anything else under « Produits » — and the
     * quantity left in stock when the article is stock-tracked.
     *
     * @return list<array<string, mixed>>
     */
    private function prescriptionLines(ConsultationDetailsDTO $details): array
    {
        if ([] === $details->prescriptionLines) {
            return [];
        }

        $stock = [];
        $rows  = $this->queryBus->ask(new ListStockItemsByClinic($details->clinicId, null, 1000));

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if ($row instanceof StockItemSummaryView && $row->trackStock) {
                $stock[$row->articleId] = (float) $row->availableQuantityAmount;
            }
        }

        $lines = [];

        foreach ($details->prescriptionLines as $line) {
            $article = null !== $line['articleId']
                ? $this->catalogItems->detail('ARTICLE', $line['articleId'], $details->clinicId)
                : null;

            $lines[] = $line + [
                'articleKind' => $article?->articleKind,
                'stock'       => null !== $line['articleId'] ? ($stock[$line['articleId']] ?? null) : null,
            ];
        }

        return $lines;
    }

    /**
     * Team notes (every clinical note but the addenda) or the addenda signed
     * after closing, newest first, with the author's name and agenda colour.
     *
     * @param array<string, array{name: string, initials: string, color: string}> $team
     *
     * @return list<array{id: string, text: string, at: string, author: string, initials: string, color: string}>
     */
    private function notes(ConsultationDetailsDTO $details, array $team, bool $addenda): array
    {
        $notes = [];

        foreach ($details->notes as $note) {
            if (('ADDENDUM' === $note['noteType']) !== $addenda) {
                continue;
            }

            $author  = $team[$note['createdByUserId']] ?? ['name' => 'Équipe', 'initials' => 'EQ', 'color' => '#94a3b8'];
            $notes[] = [
                'id'       => $note['id'],
                'text'     => $note['content'],
                'at'       => $note['createdAt'],
                'author'   => $author['name'],
                'initials' => $author['initials'],
                'color'    => $author['color'],
            ];
        }

        return array_reverse($notes);
    }

    private static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/[\s.\-_]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $word): bool => !\in_array(mb_strtolower($word), ['dr', 'pr'], true),
        ));

        if (\count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[\count($words) - 1], 0, 1));
        }

        return mb_strtoupper(mb_substr($words[0] ?? $name, 0, 2));
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
