<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Context\Consultation\Application\Service\DisplayedTotals;
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
        $currency = self::DEFAULT_CURRENCY;
        $amounts  = ['excl' => [], 'incl' => []];

        foreach ($details->billingLines as $line) {
            $currency = $line['currency'];
            $mode     = 'incl' === $line['unitPriceMode'] ? 'incl' : 'excl';
            $category = $line['taxCategoryCode'] ?? '';

            $amounts[$mode][$category] = ($amounts[$mode][$category] ?? 0) + $line['totalMinorUnits'];
        }

        /** @var array<string, float|null> $ratesByCategory */
        $ratesByCategory = [];

        ['ht' => $totalHt, 'tva' => $totalTva] = DisplayedTotals::of(
            $amounts['excl'],
            $amounts['incl'],
            function (string $category) use (&$ratesByCategory, $clinicId): ?float {
                if (!\array_key_exists($category, $ratesByCategory)) {
                    $ratesByCategory[$category] = $this->taxRates->effectiveRatePercent($category, $clinicId);
                }

                return $ratesByCategory[$category];
            },
        );

        return [
            'totalHtMinorUnits'  => $totalHt,
            'totalTvaMinorUnits' => $totalTva,
            'totalTtcMinorUnits' => $totalHt + $totalTva,
            'currency'           => $currency,
        ];
    }
}
