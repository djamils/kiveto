<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByTargetSpecies;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListMarketingAuthorizationsByTargetSpecies implements QueryInterface
{
    public function __construct(
        public string $targetSpeciesCode,
    ) {
    }
}
