<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem;

use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdatePriceListItemHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(UpdatePriceListItem $command): void
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $priceList = $this->priceListRepository->findById(PriceListId::fromString($command->priceListId), $clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($command->priceListId);
        }

        $taxOverride = null !== $command->taxCategoryOverride
            ? TaxCategoryCode::fromString($command->taxCategoryOverride)
            : null;

        $priceList->updateItem(
            PriceListItemId::fromString($command->priceListItemId),
            Money::fromMinorUnits(
                $command->netPriceMinorUnits,
                CurrencyCode::fromString($command->netPriceCurrency),
            ),
            $taxOverride,
            $this->clock->now(),
        );

        $this->priceListRepository->save($priceList);
        $this->domainEventPublisher->publish($priceList);
    }
}
