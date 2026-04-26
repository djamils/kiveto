<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Exception;

final class ICADLookupNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('ICADLookup "%s" not found.', $id));
    }
}
