<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\Shared\Exception;

final class InvalidSupplierIdException extends \InvalidArgumentException
{
    public function __construct(string $value)
    {
        parent::__construct(\sprintf('Invalid supplier ID: "%s". Expected a valid UUID (v4 or v7).', $value));
    }
}
