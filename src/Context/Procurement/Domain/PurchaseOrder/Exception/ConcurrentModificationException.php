<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Exception;

final class ConcurrentModificationException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Concurrent modification detected for aggregate "%s".', $id));
    }
}
