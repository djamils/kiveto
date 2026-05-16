<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByActiveSubstance;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListMarketingAuthorizationsByActiveSubstance implements QueryInterface
{
    public function __construct(
        public string $activeSubstanceId,
    ) {
    }
}
