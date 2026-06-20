<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\Exception;

final class DuplicateSupplierAccountException extends \RuntimeException
{
    public function __construct(string $clinicId, string $supplierId)
    {
        parent::__construct(\sprintf('A supplier account for clinic "%s" and supplier "%s" already exists.', $clinicId, $supplierId));
    }
}
