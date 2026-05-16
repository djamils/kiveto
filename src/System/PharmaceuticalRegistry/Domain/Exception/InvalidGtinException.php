<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class InvalidGtinException extends \DomainException
{
    public function __construct(string $gtin)
    {
        parent::__construct(\sprintf('Invalid GTIN: "%s". Must be 8 to 14 digits.', $gtin));
    }
}
