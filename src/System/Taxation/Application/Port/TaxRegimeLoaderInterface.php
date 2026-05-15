<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Port;

use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

interface TaxRegimeLoaderInterface
{
    public function load(TaxRegimeId $regimeId): TaxRegimeBlueprint;
}
