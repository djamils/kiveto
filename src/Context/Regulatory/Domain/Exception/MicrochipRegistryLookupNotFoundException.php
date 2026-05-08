<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Exception;

final class MicrochipRegistryLookupNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('MicrochipRegistryLookup "%s" not found.', $id));
    }
}
