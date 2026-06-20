<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Exception;

final class EmptyPurchaseOrderException extends \LogicException
{
    public function __construct(string $orderId)
    {
        parent::__construct(\sprintf('Purchase order "%s" has no active lines and cannot be submitted.', $orderId));
    }
}
