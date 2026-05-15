<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\ResolveTax;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

final readonly class ResolveTax implements QueryInterface
{
    public function __construct(
        public readonly Money $netAmount,
        public readonly TaxCategoryCode $categoryCode,
        public readonly TaxRegimeId $regimeId,
        public readonly FiscalContext $fiscalContext,
    ) {
    }
}
