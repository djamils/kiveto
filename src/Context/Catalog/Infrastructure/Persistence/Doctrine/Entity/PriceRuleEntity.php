<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_prrule_pricelist', columns: ['price_list_id'])]
class PriceRuleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'price_list_id', type: UuidType::NAME)]
    private Uuid $priceListId;

    #[ORM\Column(name: 'rule_type', type: 'string', length: 30)]
    private string $ruleType;

    #[ORM\Column(name: 'condition_json', type: 'json')]
    private string $conditionJson;

    #[ORM\Column(name: 'adjustment_json', type: 'json')]
    private string $adjustmentJson;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getPriceListId(): Uuid
    {
        return $this->priceListId;
    }

    public function setPriceListId(Uuid $priceListId): void
    {
        $this->priceListId = $priceListId;
    }

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function setRuleType(string $ruleType): void
    {
        $this->ruleType = $ruleType;
    }

    public function getConditionJson(): string
    {
        return $this->conditionJson;
    }

    public function setConditionJson(string $conditionJson): void
    {
        $this->conditionJson = $conditionJson;
    }

    public function getAdjustmentJson(): string
    {
        return $this->adjustmentJson;
    }

    public function setAdjustmentJson(string $adjustmentJson): void
    {
        $this->adjustmentJson = $adjustmentJson;
    }
}
