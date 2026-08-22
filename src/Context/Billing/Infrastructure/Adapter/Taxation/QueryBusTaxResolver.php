<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Taxation;

use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Application\Query\ResolveTax\ResolveTax;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

/**
 * Splits a net amount into net + VAT through the Taxation BC.
 *
 * When the rules cannot price a line — an unknown category, a clinic whose
 * regime is not configured — the line is billed untaxed rather than blocking
 * the whole checkout. That is visible on the invoice (0 %), which is far better
 * than a receptionist unable to take the money.
 */
final class QueryBusTaxResolver implements TaxResolverInterface
{
    /** @var array<string, ClinicDto|null> */
    private array $clinicCache = [];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function resolve(
        int $netMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
        string $clinicId,
    ): ResolvedTaxDto {
        if (null === $taxCategoryCode || '' === $taxCategoryCode) {
            return ResolvedTaxDto::untaxed($netMinorUnits);
        }

        $clinic = $this->clinic($clinicId);

        if (null === $clinic) {
            return ResolvedTaxDto::untaxed($netMinorUnits);
        }

        try {
            $result = $this->queryBus->ask(new ResolveTax(
                netAmount: Money::fromMinorUnits($netMinorUnits, CurrencyCode::fromString($currency)),
                categoryCode: TaxCategoryCode::fromString($taxCategoryCode),
                regimeId: TaxRegimeId::fromString($this->regimeIdFor($clinic)),
                fiscalContext: FiscalContext::minimal(
                    CountryCode::fromString($clinic->countryCode),
                    $this->clock->now(),
                ),
            ));
        } catch (\Throwable) {
            return ResolvedTaxDto::untaxed($netMinorUnits);
        }

        if (!$result instanceof TaxApplication) {
            return ResolvedTaxDto::untaxed($netMinorUnits);
        }

        return new ResolvedTaxDto(
            netMinorUnits: $result->netAmount()->minorUnits(),
            taxMinorUnits: $result->taxAmount()->minorUnits(),
            grossMinorUnits: $result->grossAmount()->minorUnits(),
            ratePercent: $result->appliedRate()->value()->toPercentString(),
        );
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
