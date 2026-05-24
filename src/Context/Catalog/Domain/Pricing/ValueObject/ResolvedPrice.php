<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final readonly class ResolvedPrice
{
    /** @param list<AppliedPriceRule> $appliedRules */
    public function __construct(
        public Money $netAmount,
        public Money $baseAmount,
        public array $appliedRules,
        public PriceListId $sourcePriceList,
        public ?PriceListItemId $sourcePriceListItem,
        public \DateTimeImmutable $resolvedAt,
    ) {
    }
}
