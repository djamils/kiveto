<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Exception;

final class PriceListNotFoundException extends \DomainException
{
    public function __construct(string $priceListId)
    {
        parent::__construct(\sprintf('Price list "%s" not found.', $priceListId));
    }
}
