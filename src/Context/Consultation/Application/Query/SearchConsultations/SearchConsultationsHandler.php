<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\SearchConsultations;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchConsultationsHandler
{
    /**
     * Currency shown for a consultation with no billing line yet.
     */
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private ConsultationReadRepositoryInterface $consultationReadRepository,
        private PatientIdsProviderInterface $patientIds,
        private TaxRateProviderInterface $taxRates,
    ) {
    }

    /**
     * @return array{items: list<ConsultationListItemView>, total: int}
     */
    public function __invoke(SearchConsultations $query): array
    {
        $criteria = new SearchConsultationsCriteria(
            searchTerm: $query->searchTerm,
            textMatchPatientIds: $this->toPatientIds($query->textMatchAnimalIds, $query->clinicId),
            restrictToPatientIds: null === $query->restrictToAnimalIds
                ? null
                : $this->toPatientIds($query->restrictToAnimalIds, $query->clinicId),
            startedAfterUtc: $query->startedAfterUtc,
            statuses: $query->statuses,
            practitionerUserIds: $query->practitionerUserIds,
            practitionerOrder: $query->practitionerOrder,
            sort: $query->sort,
            direction: $query->direction,
            page: $query->page,
            limit: $query->limit,
        );

        $result = $this->consultationReadRepository->search(
            ClinicId::fromString($query->clinicId),
            $criteria,
        );

        /** @var array<string, float|null> $ratesByCategory */
        $ratesByCategory = [];
        $items           = [];

        foreach ($result['items'] as $row) {
            $items[] = $this->toItemView($row, $query->clinicId, $ratesByCategory);
        }

        return ['items' => $items, 'total' => $result['total']];
    }

    /**
     * @param list<string> $animalIds
     *
     * @return list<string>
     */
    private function toPatientIds(array $animalIds, string $clinicId): array
    {
        if ([] === $animalIds) {
            return [];
        }

        return $this->patientIds->findPatientIdsForAnimals($animalIds, $clinicId);
    }

    /**
     * @param array<string, float|null> $ratesByCategory rate cache, shared across the page
     */
    private function toItemView(
        ConsultationListRow $row,
        string $clinicId,
        array &$ratesByCategory,
    ): ConsultationListItemView {
        $totalHt  = 0;
        $totalTva = 0;

        foreach ($row->htByTaxCategory as $categoryCode => $ht) {
            $totalHt += $ht;

            if ('' === $categoryCode) {
                continue;
            }

            if (!\array_key_exists($categoryCode, $ratesByCategory)) {
                $ratesByCategory[$categoryCode] = $this->taxRates->effectiveRatePercent($categoryCode, $clinicId);
            }

            $rate = $ratesByCategory[$categoryCode];

            if (null === $rate) {
                continue;
            }

            $totalTva += (int) round($ht * $rate / 100);
        }

        return new ConsultationListItemView(
            consultationId: $row->consultationId,
            patientId: $row->patientId,
            practitionerUserId: $row->practitionerUserId,
            status: $row->status,
            startedAtUtc: $row->startedAtUtc,
            closedAtUtc: $row->closedAtUtc,
            chiefComplaint: $row->chiefComplaint,
            motifs: $row->motifs,
            totalHtMinorUnits: $totalHt,
            totalTvaMinorUnits: $totalTva,
            totalTtcMinorUnits: $totalHt + $totalTva,
            currency: '' !== $row->currency ? $row->currency : self::DEFAULT_CURRENCY,
        );
    }
}
