<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetConsultationDetailsHandler
{
    /**
     * Currency used for an empty billing draft, where no line carries one yet.
     */
    private const string DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private ConsultationReadRepositoryInterface $consultationReadRepository,
        private TaxRateProviderInterface $taxRates,
    ) {
    }

    public function __invoke(GetConsultationDetails $query): ConsultationDetailsDTO
    {
        $details = $this->consultationReadRepository->findById(
            ConsultationId::fromString($query->consultationId),
            ClinicId::fromString($query->clinicId),
        );

        return $details->withTotals($this->computeTotals($details, $query->clinicId));
    }

    /**
     * @return array{
     *     totalHtMinorUnits: int,
     *     totalTvaMinorUnits: int,
     *     totalTtcMinorUnits: int,
     *     currency: string
     * }
     */
    private function computeTotals(ConsultationDetailsDTO $details, string $clinicId): array
    {
        $totalHt  = 0;
        $totalTva = 0;
        $currency = self::DEFAULT_CURRENCY;

        /** @var array<string, float|null> $ratesByCategory */
        $ratesByCategory = [];

        foreach ($details->billingLines as $line) {
            $totalHt += $line['totalMinorUnits'];
            $currency = $line['currency'];

            $categoryCode = $line['taxCategoryCode'];

            if (null === $categoryCode) {
                continue;
            }

            if (!\array_key_exists($categoryCode, $ratesByCategory)) {
                $ratesByCategory[$categoryCode] = $this->taxRates->effectiveRatePercent($categoryCode, $clinicId);
            }

            $rate = $ratesByCategory[$categoryCode];

            if (null === $rate) {
                continue;
            }

            $totalTva += (int) round($line['totalMinorUnits'] * $rate / 100);
        }

        return [
            'totalHtMinorUnits'  => $totalHt,
            'totalTvaMinorUnits' => $totalTva,
            'totalTtcMinorUnits' => $totalHt + $totalTva,
            'currency'           => $currency,
        ];
    }
}
