<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class PurchaseOrderClosedOrCancelledException extends \RuntimeException
{
    public function __construct(string $orderId, string $status)
    {
        parent::__construct(\sprintf('Purchase order "%s" is %s and cannot receive goods.', $orderId, $status));
    }
}
