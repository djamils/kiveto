<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\AddPriceRule;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPriceRule implements CommandInterface
{
    public function __construct(
        public string $priceListId,
        public string $clinicId,
        public string $ruleType,
        public bool $appliesToAllItems,
        /** @var list<array{type: string, id: string}> */
        public array $itemRefs,
        public ?bool $isUrgency,
        public ?string $animalSize,
        public ?string $speciesGroup,
        public ?string $discountCode,
        public string $adjustmentType,
        public string $adjustmentValue,
    ) {
    }
}
