<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\RemovePackageComponent;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePackageComponent implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $componentId,
        public string $clinicId,
    ) {
    }
}
