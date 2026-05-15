<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class UnknownTaxCategoryException extends TaxResolutionException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('Unknown tax category: "%s".', $code));
    }
}
