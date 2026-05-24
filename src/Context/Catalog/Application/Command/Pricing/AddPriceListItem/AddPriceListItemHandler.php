<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\AddPriceListItem;

use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddPriceListItemHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(AddPriceListItem $command): void
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $priceList = $this->priceListRepository->findById(PriceListId::fromString($command->priceListId), $clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($command->priceListId);
        }

        $taxOverride = null !== $command->taxCategoryOverride
            ? TaxCategoryCode::fromString($command->taxCategoryOverride)
            : null;

        $item = PriceListItem::create(
            id: PriceListItemId::fromString($this->uuidGenerator->generate()),
            itemRef: CatalogItemRef::fromPrimitives($command->itemType, $command->itemId),
            netPrice: Money::fromMinorUnits(
                $command->netPriceMinorUnits,
                CurrencyCode::fromString($command->netPriceCurrency),
            ),
            taxCategoryOverride: $taxOverride,
        );

        $priceList->addItem($item, $this->clock->now());

        $this->priceListRepository->save($priceList);
        $this->domainEventPublisher->publish($priceList);
    }
}
