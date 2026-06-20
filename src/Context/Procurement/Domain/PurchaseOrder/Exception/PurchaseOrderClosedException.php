<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Exception;

final class PurchaseOrderClosedException extends \LogicException
{
    public function __construct(string $orderId)
    {
        parent::__construct(\sprintf('Purchase order "%s" is closed.', $orderId));
    }
}
