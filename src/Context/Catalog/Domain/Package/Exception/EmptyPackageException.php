<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Exception;

final class EmptyPackageException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('A package must have at least one component.');
    }
}
