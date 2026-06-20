<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Exception;

final class SupplierNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Supplier "%s" not found.', $id));
    }
}
