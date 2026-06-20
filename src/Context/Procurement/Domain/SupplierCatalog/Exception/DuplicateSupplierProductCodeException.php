<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\Exception;

final class DuplicateSupplierProductCodeException extends \RuntimeException
{
    public function __construct(string $supplierId, string $code)
    {
        parent::__construct(
            \sprintf(
                'A catalog entry with product code "%s" already exists for supplier "%s".',
                $code,
                $supplierId,
            ),
        );
    }
}
