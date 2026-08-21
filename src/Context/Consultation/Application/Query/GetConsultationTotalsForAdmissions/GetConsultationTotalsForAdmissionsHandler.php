<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
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

        foreach ($rows as $admissionId => $row) {
            $totalHt  = 0;
            $totalTva = 0;

            foreach ($row['htByTaxCategory'] as $categoryCode => $ht) {
                $totalHt += $ht;

                if ('' === $categoryCode) {
                    continue;
                }

                if (!\array_key_exists($categoryCode, $ratesByCategory)) {
                    $ratesByCategory[$categoryCode] = $this->taxRates->effectiveRatePercent(
                        $categoryCode,
                        $query->clinicId,
                    );
                }

                $rate = $ratesByCategory[$categoryCode];

                if (null === $rate) {
                    continue;
                }

                $totalTva += (int) round($ht * $rate / 100);
            }

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
