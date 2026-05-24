<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\RestorePackage;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RestorePackage implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
    ) {
    }
}
