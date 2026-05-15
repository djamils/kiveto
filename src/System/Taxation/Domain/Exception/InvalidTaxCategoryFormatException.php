<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class InvalidTaxCategoryFormatException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf(
            'Invalid tax category code format: "%s". Expected: ^[a-z]+(\.[a-z_]+)+$',
            $code
        ));
    }
}
