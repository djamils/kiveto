<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class NoApplicableRateException extends TaxResolutionException
{
    public function __construct(string $category, string $regimeId, \DateTimeImmutable $date)
    {
        parent::__construct(\sprintf(
            'No applicable tax rate for category "%s" in regime "%s" on %s.',
            $category,
            $regimeId,
            $date->format('Y-m-d')
        ));
    }
}
