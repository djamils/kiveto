<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Exception;

final class AllocationException extends \DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(\sprintf('Allocation failed: %s', $reason));
    }
}
