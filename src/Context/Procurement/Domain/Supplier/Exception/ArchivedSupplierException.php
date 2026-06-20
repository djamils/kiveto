<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Exception;

final class ArchivedSupplierException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Supplier "%s" is archived and cannot be modified.', $id));
    }
}
