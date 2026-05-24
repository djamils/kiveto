<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\ArchivePackage;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ArchivePackage implements CommandInterface
{
    public function __construct(
        public string $packageId,
        public string $clinicId,
    ) {
    }
}
