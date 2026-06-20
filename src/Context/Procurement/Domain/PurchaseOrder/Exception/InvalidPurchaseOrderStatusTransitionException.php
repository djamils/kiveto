<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Exception;

final class InvalidPurchaseOrderStatusTransitionException extends \LogicException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
