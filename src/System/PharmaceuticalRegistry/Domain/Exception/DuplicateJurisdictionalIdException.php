<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Exception;

final class DuplicateJurisdictionalIdException extends \DomainException
{
    public function __construct(string $jurisdictionCode, string $authorityCode, string $authorityIdentifier)
    {
        parent::__construct(\sprintf(
            'Duplicate jurisdictional identifier: %s/%s/%s.',
            $jurisdictionCode,
            $authorityCode,
            $authorityIdentifier,
        ));
    }
}
