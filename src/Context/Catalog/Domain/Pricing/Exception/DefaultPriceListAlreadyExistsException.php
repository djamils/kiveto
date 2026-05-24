<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Exception;

final class DefaultPriceListAlreadyExistsException extends \DomainException
{
    public function __construct(string $clinicId)
    {
        parent::__construct(\sprintf('A default price list already exists for clinic "%s".', $clinicId));
    }
}
