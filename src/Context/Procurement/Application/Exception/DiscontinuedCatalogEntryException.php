<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class DiscontinuedCatalogEntryException extends \RuntimeException
{
    public function __construct(string $entryId)
    {
        parent::__construct(\sprintf('Catalog entry "%s" is discontinued and cannot be ordered.', $entryId));
    }
}
