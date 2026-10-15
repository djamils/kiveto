<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

use App\Context\Consultation\Application\Port\ActCategoryProviderInterface;
use App\Context\Consultation\Application\Port\AdmissionFactsProviderInterface;
use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListConsultationRegisterHandler
{
    /**
     * Currency shown for a consultation with no billing line yet.
     */
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private ConsultationReadRepositoryInterface $consultations,
        private PatientIdsProviderInterface $patientIds,
        private AdmissionFactsProviderInterface $admissions,
        private ActCategoryProviderInterface $acts,
        private TaxRateProviderInterface $taxRates,
    ) {
    }

    public function __invoke(ListConsultationRegister $query): ConsultationRegisterPage
    {
        if (null !== $query->kind && !\in_array($query->kind, ConsultationRegisterCriteria::kinds(), true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported kind "%s".', $query->kind));
        }

        $clinicId = ClinicId::fromString($query->clinicId);

        // An open consultation whose visit is over waits to be closed, like
        // one left open from a previous day.
        $openAdmissions = $this->consultations->openConsultationAdmissionIds($clinicId);
        $departed       = [] === $openAdmissions ? [] : $this->admissions->closedAmong($query->clinicId, $openAdmissions);

        $codes = $this->acts->actCodesByCategory(
            $query->clinicId,
            [ActCategoryProviderInterface::SURGERY, ActCategoryProviderInterface::LABORATORY],
        );
        $surgeryCodes = array_keys(array_filter($codes, static fn (string $c): bool => ActCategoryProviderInterface::SURGERY === $c));
        $labCodes     = array_keys(array_filter($codes, static fn (string $c): bool => ActCategoryProviderInterface::LABORATORY === $c));

        $result = $this->consultations->register($clinicId, new ConsultationRegisterCriteria(
            dayStartUtc: $query->dayStartUtc,
            searchTerm: $query->searchTerm,
            textMatchPatientIds: [] === $query->textMatchAnimalIds
                ? []
                : $this->patientIds->findPatientIdsForAnimals($query->textMatchAnimalIds, $query->clinicId),
            startedAfterUtc: $query->startedAfterUtc,
            stage: $query->stage,
            departedAdmissionIds: $departed,
            practitionerUserId: $query->practitionerUserId,
            restrictToAdmissionIds: $this->admissionRestriction($query),
            restrictToActCodes: match ($query->kind) {
                ConsultationRegisterCriteria::KIND_SURGERY => array_map('strval', $surgeryCodes),
                ConsultationRegisterCriteria::KIND_LAB     => array_map('strval', $labCodes),
                default                                    => null,
            },
            consultationIds: $query->consultationIds,
            page: $query->page,
            limit: $query->limit,
        ));

        $pageAdmissions = array_values(array_unique(array_map(
            static fn (ConsultationRegisterRow $row): string => $row->admissionId,
            $result['items'],
        )));
        $emergencies = [] === $pageAdmissions ? [] : $this->admissions->emergenciesAmong($query->clinicId, $pageAdmissions);

        /** @var array<string, float|null> $rates */
        $rates = [];
        $items = [];

        foreach ($result['items'] as $row) {
            $items[] = $this->item($row, $query, $departed, $emergencies, $codes, $rates);
        }

        $counters = $this->consultations->registerCounters($clinicId, $query->dayStartUtc, $departed);

        return new ConsultationRegisterPage(
            items: $items,
            total: $result['total'],
            page: $query->page,
            perPage: $query->limit,
            inProgress: $counters['inProgress'],
            toClose: $counters['toClose'],
            today: $counters['today'],
            recorded: $counters['recorded'],
        );
    }

    /**
     * Visits a filter keeps: those of another context's filter, narrowed to
     * the emergencies when « Urgences » is picked.
     *
     * @return ?list<string>
     */
    private function admissionRestriction(ListConsultationRegister $query): ?array
    {
        if (ConsultationRegisterCriteria::KIND_URGENT !== $query->kind) {
            return $query->restrictToAdmissionIds;
        }

        $emergencies = $this->admissions->emergencyAdmissionIds($query->clinicId);

        return null === $query->restrictToAdmissionIds
            ? $emergencies
            : array_values(array_intersect($emergencies, $query->restrictToAdmissionIds));
    }

    /**
     * @param list<string>              $departed
     * @param list<string>              $emergencies
     * @param array<string, string>     $codes       act code => category
     * @param array<string, float|null> $rates       rate cache, shared across the page
     */
    private function item(
        ConsultationRegisterRow $row,
        ListConsultationRegister $query,
        array $departed,
        array $emergencies,
        array $codes,
        array &$rates,
    ): ConsultationRegisterItem {
        $totalHt  = 0;
        $totalTva = 0;

        foreach ($row->htByTaxCategory as $category => $ht) {
            $totalHt += $ht;

            if ('' === $category) {
                continue;
            }

            if (!\array_key_exists($category, $rates)) {
                $rates[$category] = $this->taxRates->effectiveRatePercent($category, $query->clinicId);
            }

            if (null !== $rates[$category]) {
                $totalTva += (int) round($ht * $rates[$category] / 100);
            }
        }

        $labs    = [];
        $surgery = false;

        foreach ($row->billedItems as $billed) {
            $category = null !== $billed['code'] ? ($codes[$billed['code']] ?? null) : null;
            $surgery  = $surgery || ActCategoryProviderInterface::SURGERY === $category;

            if (ActCategoryProviderInterface::LABORATORY === $category) {
                $labs[] = $billed['label'];
            }
        }

        $started = new \DateTimeImmutable($row->startedAtUtc, new \DateTimeZone('UTC'));
        $closed  = null !== $row->closedAtUtc ? new \DateTimeImmutable($row->closedAtUtc, new \DateTimeZone('UTC')) : null;

        return new ConsultationRegisterItem(
            consultationId: $row->consultationId,
            admissionId: $row->admissionId,
            patientId: $row->patientId,
            practitionerUserId: $row->practitionerUserId,
            status: $row->status,
            stage: match (true) {
                'CLOSED' === $row->status => ConsultationRegisterCriteria::STAGE_CLOSED,
                $started < $query->dayStartUtc,
                \in_array($row->admissionId, $departed, true) => ConsultationRegisterCriteria::STAGE_TO_CLOSE,
                default                                       => ConsultationRegisterCriteria::STAGE_IN_PROGRESS,
            },
            startedAtUtc: $row->startedAtUtc,
            closedAtUtc: $row->closedAtUtc,
            durationMinutes: null !== $closed ? max(1, intdiv($closed->getTimestamp() - $started->getTimestamp(), 60)) : null,
            chiefComplaint: $row->chiefComplaint,
            summary: $row->summary,
            motifs: $row->motifs,
            primaryDiagnosis: $row->primaryDiagnosis,
            weightKg: $row->weightKg,
            isUrgent: \in_array($row->admissionId, $emergencies, true),
            hasSurgery: $surgery,
            labAnalyses: $labs,
            billedItems: $row->billedItems,
            totalHtMinorUnits: $totalHt,
            totalTvaMinorUnits: $totalTva,
            totalTtcMinorUnits: $totalHt + $totalTva,
            currency: '' !== $row->currency ? $row->currency : self::DEFAULT_CURRENCY,
        );
    }
}
