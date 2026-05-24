<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Exception;

final class PackageNotFoundException extends \DomainException
{
    public function __construct(string $packageId)
    {
        parent::__construct(\sprintf('Package "%s" not found.', $packageId));
    }
}
