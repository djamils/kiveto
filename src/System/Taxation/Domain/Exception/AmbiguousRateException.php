<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class AmbiguousRateException extends TaxResolutionException
{
    /** @param list<string> $rateIds */
    public function __construct(string $category, string $regimeId, array $rateIds)
    {
        parent::__construct(\sprintf(
            'Ambiguous tax rates for category "%s" in regime "%s": %s.',
            $category,
            $regimeId,
            implode(', ', $rateIds)
        ));
    }
}
