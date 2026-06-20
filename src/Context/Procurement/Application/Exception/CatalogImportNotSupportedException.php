<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class CatalogImportNotSupportedException extends \RuntimeException
{
    public function __construct(string $adapterIdentifier)
    {
        parent::__construct(\sprintf('Catalog import is not supported by adapter "%s".', $adapterIdentifier));
    }
}
