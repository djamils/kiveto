<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Article\Exception;

final class InvalidDrugPropertiesException extends \DomainException
{
    public function __construct(string $reason)
    {
        parent::__construct(\sprintf('Invalid drug properties: %s', $reason));
    }
}
