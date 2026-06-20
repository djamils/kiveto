<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ChangeSupplierIntegrationMode;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeSupplierIntegrationMode implements CommandInterface
{
    public function __construct(
        public string $supplierId,
        public string $newMode,
        public ?string $adapterIdentifier,
    ) {
    }
}
