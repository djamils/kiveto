<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Exception;

final class PurchaseOrderNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Purchase order "%s" not found.', $id));
    }
}
