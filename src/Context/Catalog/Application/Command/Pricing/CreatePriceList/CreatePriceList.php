<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Pricing\CreatePriceList;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreatePriceList implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
        public bool $isDefault,
    ) {
    }
}
