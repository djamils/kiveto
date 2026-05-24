<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Service;

use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Pricing\Exception\NoPriceFoundForItemException;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\Service\PriceCalculator;
use App\Context\Catalog\Domain\Pricing\ValueObject\PricingContext;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemType;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\Money;

final class PriceResolver
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly ActRepositoryInterface $actRepository,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly PriceCalculator $priceCalculator,
        private readonly RoundingPolicyRegistry $roundingPolicyRegistry,
        private readonly ClockInterface $clock,
    ) {
    }

    public function resolve(CatalogItemRef $itemRef, PricingContext $context): ResolvedPrice
    {
        $clinicId = ClinicId::fromString($context->priceListId->toString());

        // 1. Load price list
        $priceList = $this->priceListRepository->findDefaultForClinic($clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($context->priceListId->toString());
        }

        // 2. Find PriceListItem for itemRef
        $foundItem = null;
        $basePrice = null;

        foreach ($priceList->items() as $item) {
            if ($item->itemRef()->equals($itemRef)) {
                $foundItem = $item;
                $basePrice = $item->netPrice();
                break;
            }
        }

        // 3. Fallback to aggregate's base price
        if (null === $basePrice) {
            $basePrice = $this->loadBasePrice($itemRef, $clinicId);
        }

        if (null === $basePrice) {
            throw new NoPriceFoundForItemException($itemRef->type()->value, $itemRef->id());
        }

        // 4. Filter rules where condition matches
        $filteredRules = array_filter(
            $priceList->rules(),
            static fn (PriceRule $rule) => $rule->condition()->matches($context, $itemRef),
        );

        /** @var list<PriceRule> $matchingRules */
        $matchingRules = array_values($filteredRules);

        // 5. Sort by applicationOrder()
        usort(
            $matchingRules,
            static fn (PriceRule $a, PriceRule $b) => $a->ruleType()->applicationOrder() <=> $b->ruleType()->applicationOrder(),
        );

        // 6. Calculate
        $rounding = $this->roundingPolicyRegistry->commercial();
        $result   = $this->priceCalculator->calculate($basePrice, $matchingRules, $rounding);

        // 7. Return ResolvedPrice
        return new ResolvedPrice(
            netAmount: $result['netAmount'],
            baseAmount: $basePrice,
            appliedRules: $result['appliedRules'],
            sourcePriceList: $priceList->id(),
            sourcePriceListItem: $foundItem?->id(),
            resolvedAt: $this->clock->now(),
        );
    }

    private function loadBasePrice(CatalogItemRef $itemRef, ClinicId $clinicId): ?Money
    {
        return match ($itemRef->type()) {
            CatalogItemType::ACT => $this->actRepository
                ->findById(ActId::fromString($itemRef->id()), $clinicId)
                ?->basePrice(),
            CatalogItemType::ARTICLE => $this->articleRepository
                ->findById(ArticleId::fromString($itemRef->id()), $clinicId)
                ?->basePrice(),
        };
    }
}
