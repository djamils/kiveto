<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ApplyStarterCatalog implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $catalogType = 'companion',
    ) {
    }
}
