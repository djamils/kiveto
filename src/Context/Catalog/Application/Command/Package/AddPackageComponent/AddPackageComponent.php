<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\AddPackageComponent;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddPackageComponent implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
        public string $itemType,
        public string $itemId,
        public int $quantity,
        public int $weight,
    ) {
    }
}
