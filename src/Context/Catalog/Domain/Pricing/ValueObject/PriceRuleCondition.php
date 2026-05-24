<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;

final class PriceRuleCondition
{
    /** @param list<CatalogItemRef> $itemRefs */
    private function __construct(
        private bool $appliesToAllItems,
        private array $itemRefs,
        private ?bool $isUrgency,
        private ?AnimalSizeCategory $animalSize,
        private ?string $speciesGroup,
        private ?string $discountCode,
    ) {
    }

    /** @param list<CatalogItemRef> $itemRefs */
    public static function create(
        bool $appliesToAllItems,
        array $itemRefs,
        ?bool $isUrgency,
        ?AnimalSizeCategory $animalSize,
        ?string $speciesGroup,
        ?string $discountCode,
    ): self {
        return new self(
            appliesToAllItems: $appliesToAllItems,
            itemRefs: $itemRefs,
            isUrgency: $isUrgency,
            animalSize: $animalSize,
            speciesGroup: $speciesGroup,
            discountCode: $discountCode,
        );
    }

    public function matches(PricingContext $context, CatalogItemRef $itemRef): bool
    {
        if (!$this->appliesToAllItems) {
            $found = false;

            foreach ($this->itemRefs as $ref) {
                if ($ref->equals($itemRef)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        if (null !== $this->isUrgency && $this->isUrgency !== $context->isUrgency) {
            return false;
        }

        if (null !== $this->animalSize && $this->animalSize !== $context->animalSize) {
            return false;
        }

        if (null !== $this->speciesGroup && $this->speciesGroup !== $context->speciesGroup) {
            return false;
        }

        if (null !== $this->discountCode && $this->discountCode !== $context->discountCode) {
            return false;
        }

        return true;
    }

    /**
     * @return array{appliesToAllItems: bool, itemRefs: list<array{type: string, id: string}>, isUrgency: bool|null, animalSize: string|null, speciesGroup: string|null, discountCode: string|null}
     */
    public function toArray(): array
    {
        return [
            'appliesToAllItems' => $this->appliesToAllItems,
            'itemRefs'          => array_map(
                static fn (CatalogItemRef $ref) => ['type' => $ref->type()->value, 'id' => $ref->id()],
                $this->itemRefs,
            ),
            'isUrgency'    => $this->isUrgency,
            'animalSize'   => $this->animalSize?->value,
            'speciesGroup' => $this->speciesGroup,
            'discountCode' => $this->discountCode,
        ];
    }

    /**
     * @param array{appliesToAllItems: bool, itemRefs: list<array{type: string, id: string}>, isUrgency: bool|null, animalSize: string|null, speciesGroup: string|null, discountCode: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        $itemRefs = array_map(
            static fn (array $r) => CatalogItemRef::fromPrimitives($r['type'], $r['id']),
            $data['itemRefs'],
        );

        return new self(
            appliesToAllItems: $data['appliesToAllItems'],
            itemRefs: $itemRefs,
            isUrgency: $data['isUrgency'],
            animalSize: null !== $data['animalSize'] ? AnimalSizeCategory::from($data['animalSize']) : null,
            speciesGroup: $data['speciesGroup'],
            discountCode: $data['discountCode'],
        );
    }

    public function appliesToAllItems(): bool
    {
        return $this->appliesToAllItems;
    }

    /** @return list<CatalogItemRef> */
    public function itemRefs(): array
    {
        return $this->itemRefs;
    }

    public function isUrgency(): ?bool
    {
        return $this->isUrgency;
    }

    public function animalSize(): ?AnimalSizeCategory
    {
        return $this->animalSize;
    }

    public function speciesGroup(): ?string
    {
        return $this->speciesGroup;
    }

    public function discountCode(): ?string
    {
        return $this->discountCode;
    }
}
