<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Query\GetEffectiveRateForUI;

final readonly class EffectiveRateResult
{
    public function __construct(
        public readonly string $ratePercent,
        public readonly string $regimeId,
    ) {
    }
}
