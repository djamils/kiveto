<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RenameSupplier;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameSupplier implements CommandInterface
{
    public function __construct(
        public string $supplierId,
        public string $newName,
    ) {
    }
}
