<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\Exception;

final class SupplierCatalogEntryNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('SupplierCatalogEntry "%s" not found.', $id));
    }
}
