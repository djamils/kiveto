<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Exception;

final class InvalidPriceAdjustmentException extends \DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(\sprintf('Invalid price adjustment: %s', $reason));
    }
}
