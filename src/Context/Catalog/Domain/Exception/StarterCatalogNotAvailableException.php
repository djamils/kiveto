<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Exception;

final class StarterCatalogNotAvailableException extends \DomainException
{
    public function __construct(string $countryCode, string $catalogType)
    {
        parent::__construct(\sprintf(
            'No starter catalog available for country "%s" and type "%s".',
            $countryCode,
            $catalogType,
        ));
    }
}
