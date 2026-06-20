<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\DiscontinueSupplierCatalogEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DiscontinueSupplierCatalogEntry implements CommandInterface
{
    public function __construct(public string $entryId)
    {
    }
}
