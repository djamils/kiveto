<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class OverlappingValidityPeriodsException extends \DomainException
{
    public function __construct(string $regimeId, string $rateId1, string $rateId2)
    {
        parent::__construct(\sprintf(
            'Overlapping validity periods detected in regime "%s" between rates "%s" and "%s".',
            $regimeId,
            $rateId1,
            $rateId2
        ));
    }
}
