<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\Event\PriceListCreated;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemRemoved;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemUpdated;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleRemoved;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class PriceList extends AggregateRoot
{
    /**
     * @param list<PriceListItem> $items
     * @param list<PriceRule>     $rules
     */
    private function __construct(
        private PriceListId $id,
        private ClinicId $clinicId,
        private PriceListName $name,
        private bool $isDefault,
        private PriceListStatus $status,
        /** @var list<PriceListItem> */
        private array $items,
        /** @var list<PriceRule> */
        private array $rules,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        PriceListId $id,
        ClinicId $clinicId,
        PriceListName $name,
        bool $isDefault,
        \DateTimeImmutable $createdAt,
    ): self {
        $priceList = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            isDefault: $isDefault,
            status: PriceListStatus::ACTIVE,
            items: [],
            rules: [],
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $priceList->recordDomainEvent(new PriceListCreated(
            priceListId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name->toString(),
            isDefault: $isDefault,
        ));

        return $priceList;
    }

    /**
     * @param list<PriceListItem> $items
     * @param list<PriceRule>     $rules
     */
    public static function reconstitute(
        PriceListId $id,
        ClinicId $clinicId,
        PriceListName $name,
        bool $isDefault,
        PriceListStatus $status,
        array $items,
        array $rules,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            isDefault: $isDefault,
            status: $status,
            items: $items,
            rules: $rules,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function addItem(PriceListItem $item, \DateTimeImmutable $updatedAt): void
    {
        $this->items[]   = $item;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new PriceListItemAdded(
            priceListId: $this->id->toString(),
            priceListItemId: $item->id()->toString(),
            itemType: $item->itemRef()->type()->value,
            itemId: $item->itemRef()->id(),
            netPriceMinorUnits: $item->netPrice()->minorUnits(),
            netPriceCurrency: $item->netPrice()->currency()->toString(),
        ));
    }

    public function updateItem(
        PriceListItemId $itemId,
        Money $netPrice,
        ?TaxCategoryCode $taxCategoryOverride,
        \DateTimeImmutable $updatedAt,
    ): void {
        foreach ($this->items as $item) {
            if ($item->id()->equals($itemId)) {
                $item->updatePrice($netPrice, $taxCategoryOverride);
                $this->updatedAt = $updatedAt;

                $this->recordDomainEvent(new PriceListItemUpdated(
                    priceListId: $this->id->toString(),
                    priceListItemId: $itemId->toString(),
                    newNetPriceMinorUnits: $netPrice->minorUnits(),
                    newNetPriceCurrency: $netPrice->currency()->toString(),
                ));

                return;
            }
        }
    }

    public function removeItem(PriceListItemId $itemId, \DateTimeImmutable $updatedAt): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->id()->equals($itemId)) {
                $remaining = $this->items;
                unset($remaining[$key]);
                /** @var list<PriceListItem> $reindexed */
                $reindexed       = array_values($remaining);
                $this->items     = $reindexed;
                $this->updatedAt = $updatedAt;

                $this->recordDomainEvent(new PriceListItemRemoved(
                    priceListId: $this->id->toString(),
                    priceListItemId: $itemId->toString(),
                ));

                return;
            }
        }
    }

    public function addRule(PriceRule $rule, \DateTimeImmutable $updatedAt): void
    {
        $this->rules[]   = $rule;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new PriceRuleAdded(
            priceListId: $this->id->toString(),
            priceRuleId: $rule->id()->toString(),
            ruleType: $rule->ruleType()->value,
        ));
    }

    public function removeRule(PriceRuleId $ruleId, \DateTimeImmutable $updatedAt): void
    {
        foreach ($this->rules as $key => $rule) {
            if ($rule->id()->equals($ruleId)) {
                $remaining = $this->rules;
                unset($remaining[$key]);
                /** @var list<PriceRule> $reindexed */
                $reindexed       = array_values($remaining);
                $this->rules     = $reindexed;
                $this->updatedAt = $updatedAt;

                $this->recordDomainEvent(new PriceRuleRemoved(
                    priceListId: $this->id->toString(),
                    priceRuleId: $ruleId->toString(),
                ));

                return;
            }
        }
    }

    public function markAsDefault(\DateTimeImmutable $updatedAt): void
    {
        $this->isDefault = true;
        $this->updatedAt = $updatedAt;
    }

    public function unmarkAsDefault(\DateTimeImmutable $updatedAt): void
    {
        $this->isDefault = false;
        $this->updatedAt = $updatedAt;
    }

    public function id(): PriceListId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function name(): PriceListName
    {
        return $this->name;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function status(): PriceListStatus
    {
        return $this->status;
    }

    /** @return list<PriceListItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<PriceRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
