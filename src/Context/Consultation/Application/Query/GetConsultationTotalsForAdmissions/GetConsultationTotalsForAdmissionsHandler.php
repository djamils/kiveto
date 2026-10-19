<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Service\DisplayedTotals;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetConsultationTotalsForAdmissionsHandler
{
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private ConsultationReadRepositoryInterface $consultationsRead,
        private TaxRateProviderInterface $taxRates,
    ) {
    }

    /**
     * @return array<string, AdmissionTotal> admissionId => total
     */
    public function __invoke(GetConsultationTotalsForAdmissions $query): array
    {
        if ([] === $query->admissionIds) {
            return [];
        }

        $rows = $this->consultationsRead->totalsForAdmissions(
            ClinicId::fromString($query->clinicId),
            $query->admissionIds,
        );

        /** @var array<string, float|null> $ratesByCategory */
        $ratesByCategory = [];
        $totals          = [];

        $rateOf = function (string $category) use (&$ratesByCategory, $query): ?float {
            if (!\array_key_exists($category, $ratesByCategory)) {
                $ratesByCategory[$category] = $this->taxRates->effectiveRatePercent($category, $query->clinicId);
            }

            return $ratesByCategory[$category];
        };

        foreach ($rows as $admissionId => $row) {
            ['ht' => $totalHt, 'tva' => $totalTva] = DisplayedTotals::of(
                $row['htByTaxCategory'],
                $row['ttcByTaxCategory'],
                $rateOf,
            );

            $totals[$admissionId] = new AdmissionTotal(
                admissionId: $admissionId,
                totalHtMinorUnits: $totalHt,
                totalTvaMinorUnits: $totalTva,
                totalTtcMinorUnits: $totalHt + $totalTva,
                currency: '' !== $row['currency'] ? $row['currency'] : self::DEFAULT_CURRENCY,
            );
        }

        return $totals;
    }
}
