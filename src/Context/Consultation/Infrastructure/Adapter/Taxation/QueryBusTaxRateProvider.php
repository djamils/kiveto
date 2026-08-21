<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Taxation;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\EffectiveRateResult;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\GetEffectiveRateForUI;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final class QueryBusTaxRateProvider implements TaxRateProviderInterface
{
    /** @var array<string, ClinicDto|null> */
    private array $clinicCache = [];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function effectiveRatePercent(string $taxCategoryCode, string $clinicId): ?float
    {
        $clinic = $this->clinic($clinicId);

        if (null === $clinic) {
            return null;
        }

        try {
            $query = new GetEffectiveRateForUI(
                categoryCode: TaxCategoryCode::fromString($taxCategoryCode),
                regimeId: TaxRegimeId::fromString($this->regimeIdFor($clinic)),
                fiscalContext: FiscalContext::minimal(
                    CountryCode::fromString($clinic->countryCode),
                    $this->clock->now(),
                ),
            );

            $result = $this->queryBus->ask($query);
        } catch (\Throwable) {
            return null;
        }

        if (!$result instanceof EffectiveRateResult) {
            return null;
        }

        return (float) $result->ratePercent;
    }

    /**
     * The regime is the clinic's country, narrowed by its jurisdiction when the
     * country has several (e.g. "FR-COR").
     */
    private function regimeIdFor(ClinicDto $clinic): string
    {
        $country = mb_strtoupper($clinic->countryCode);

        if (null === $clinic->jurisdictionCode || '' === $clinic->jurisdictionCode) {
            return $country;
        }

        return $country . '-' . mb_strtoupper($clinic->jurisdictionCode);
    }

    private function clinic(string $clinicId): ?ClinicDto
    {
        if (\array_key_exists($clinicId, $this->clinicCache)) {
            return $this->clinicCache[$clinicId];
        }

        try {
            $dto = $this->queryBus->ask(new GetClinic($clinicId));
        } catch (\Throwable) {
            $dto = null;
        }

        return $this->clinicCache[$clinicId] = $dto instanceof ClinicDto ? $dto : null;
    }
}
