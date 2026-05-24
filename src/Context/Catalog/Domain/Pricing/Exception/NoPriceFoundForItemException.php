<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Exception;

final class NoPriceFoundForItemException extends \DomainException
{
    public function __construct(string $itemType, string $itemId)
    {
        parent::__construct(\sprintf('No price found for item "%s/%s".', $itemType, $itemId));
    }
}
