<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\RemovePriceRule;

use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemovePriceRuleHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RemovePriceRule $command): void
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $priceList = $this->priceListRepository->findById(PriceListId::fromString($command->priceListId), $clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($command->priceListId);
        }

        $priceList->removeRule(PriceRuleId::fromString($command->priceRuleId), $this->clock->now());

        $this->priceListRepository->save($priceList);
        $this->domainEventPublisher->publish($priceList);
    }
}
