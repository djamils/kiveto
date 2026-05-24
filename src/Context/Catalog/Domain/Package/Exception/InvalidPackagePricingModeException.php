<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Exception;

final class InvalidPackagePricingModeException extends \DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(\sprintf('Invalid package pricing mode: %s', $reason));
    }
}
