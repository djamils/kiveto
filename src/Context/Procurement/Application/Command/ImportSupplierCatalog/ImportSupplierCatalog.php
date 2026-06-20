<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ImportSupplierCatalog;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ImportSupplierCatalog implements CommandInterface
{
    public function __construct(public string $supplierId)
    {
    }
}
