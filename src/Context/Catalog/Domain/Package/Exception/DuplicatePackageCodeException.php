<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Exception;

final class DuplicatePackageCodeException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('A package with code "%s" already exists for this clinic.', $code));
    }
}
