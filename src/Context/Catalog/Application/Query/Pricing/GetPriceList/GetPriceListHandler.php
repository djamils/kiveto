<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\GetPriceList;

use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPriceListHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
    ) {
    }

    public function __invoke(GetPriceList $query): PriceListView
    {
        $clinicId  = ClinicId::fromString($query->clinicId);
        $priceList = $this->priceListRepository->findById(PriceListId::fromString($query->priceListId), $clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($query->priceListId);
        }

        $items = array_map(
            static fn (PriceListItem $item) => [
                'id'                  => $item->id()->toString(),
                'itemType'            => $item->itemRef()->type()->value,
                'itemId'              => $item->itemRef()->id(),
                'netPriceMinorUnits'  => $item->netPrice()->minorUnits(),
                'netPriceCurrency'    => $item->netPrice()->currency()->toString(),
                'taxCategoryOverride' => $item->taxCategoryOverride()?->toString(),
            ],
            $priceList->items(),
        );

        $rules = array_map(
            static fn (PriceRule $rule) => [
                'id'         => $rule->id()->toString(),
                'ruleType'   => $rule->ruleType()->value,
                'condition'  => $rule->condition()->toArray(),
                'adjustment' => $rule->adjustment()->toArray(),
            ],
            $priceList->rules(),
        );

        return new PriceListView(
            id: $priceList->id()->toString(),
            clinicId: $priceList->clinicId()->toString(),
            name: $priceList->name()->toString(),
            isDefault: $priceList->isDefault(),
            status: $priceList->status()->value,
            items: $items,
            rules: $rules,
            createdAt: $priceList->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $priceList->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
