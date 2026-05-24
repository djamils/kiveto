<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\AddPriceRule;

use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\AdjustmentType;
use App\Context\Catalog\Domain\Pricing\ValueObject\AnimalSizeCategory;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceAdjustment;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleCondition;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleType;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddPriceRuleHandler
{
    public function __construct(
        private readonly PriceListRepositoryInterface $priceListRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(AddPriceRule $command): void
    {
        $clinicId  = ClinicId::fromString($command->clinicId);
        $priceList = $this->priceListRepository->findById(PriceListId::fromString($command->priceListId), $clinicId);

        if (null === $priceList) {
            throw new PriceListNotFoundException($command->priceListId);
        }

        $itemRefs = array_map(
            static fn (array $r) => CatalogItemRef::fromPrimitives($r['type'], $r['id']),
            $command->itemRefs,
        );

        $condition = PriceRuleCondition::create(
            appliesToAllItems: $command->appliesToAllItems,
            itemRefs: $itemRefs,
            isUrgency: $command->isUrgency,
            animalSize: null !== $command->animalSize ? AnimalSizeCategory::from($command->animalSize) : null,
            speciesGroup: $command->speciesGroup,
            discountCode: $command->discountCode,
        );

        $adjustment = match (AdjustmentType::from($command->adjustmentType)) {
            AdjustmentType::COEFFICIENT           => PriceAdjustment::coefficient($command->adjustmentValue),
            AdjustmentType::FIXED_AMOUNT_DISCOUNT => PriceAdjustment::fromArray([
                'type'  => AdjustmentType::FIXED_AMOUNT_DISCOUNT->value,
                'value' => $command->adjustmentValue,
            ]),
        };

        $rule = PriceRule::create(
            id: PriceRuleId::fromString($this->uuidGenerator->generate()),
            ruleType: PriceRuleType::from($command->ruleType),
            condition: $condition,
            adjustment: $adjustment,
        );

        $priceList->addRule($rule, $this->clock->now());

        $this->priceListRepository->save($priceList);
        $this->domainEventPublisher->publish($priceList);
    }
}
