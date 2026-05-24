<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceAdjustment;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleCondition;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleType;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PriceRuleEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PriceRuleMapper
{
    public function toEntity(PriceRule $rule, PriceListId $priceListId, ?PriceRuleEntity $existing = null): PriceRuleEntity
    {
        $entity = $existing ?? new PriceRuleEntity();

        $entity->setId(Uuid::fromString($rule->id()->toString()));
        $entity->setPriceListId(Uuid::fromString($priceListId->toString()));
        $entity->setRuleType($rule->ruleType()->value);
        $entity->setConditionJson((string) json_encode($rule->condition()->toArray(), \JSON_THROW_ON_ERROR));
        $entity->setAdjustmentJson((string) json_encode($rule->adjustment()->toArray(), \JSON_THROW_ON_ERROR));

        return $entity;
    }

    public function toDomain(PriceRuleEntity $entity): PriceRule
    {
        /** @var array{appliesToAllItems: bool, itemRefs: list<array{type: string, id: string}>, isUrgency: bool|null, animalSize: string|null, speciesGroup: string|null, discountCode: string|null} $conditionData */
        $conditionData = json_decode($entity->getConditionJson(), true, 512, \JSON_THROW_ON_ERROR);

        /** @var array{type: string, value: string} $adjustmentData */
        $adjustmentData = json_decode($entity->getAdjustmentJson(), true, 512, \JSON_THROW_ON_ERROR);

        return PriceRule::create(
            id: PriceRuleId::fromString($entity->getId()->toString()),
            ruleType: PriceRuleType::from($entity->getRuleType()),
            condition: PriceRuleCondition::fromArray($conditionData),
            adjustment: PriceAdjustment::fromArray($adjustmentData),
        );
    }
}
