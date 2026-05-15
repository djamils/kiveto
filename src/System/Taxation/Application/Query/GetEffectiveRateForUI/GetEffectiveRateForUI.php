<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\GetEffectiveRateForUI;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class GetEffectiveRateForUI implements QueryInterface
{
    public function __construct(
        public readonly TaxCategoryCode $categoryCode,
        public readonly TaxRegimeId $regimeId,
        public readonly FiscalContext $fiscalContext,
    ) {
    }
}
