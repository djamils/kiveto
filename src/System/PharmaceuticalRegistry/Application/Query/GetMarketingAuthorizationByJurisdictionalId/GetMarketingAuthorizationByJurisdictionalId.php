<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationByJurisdictionalId;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetMarketingAuthorizationByJurisdictionalId implements QueryInterface
{
    public function __construct(
        public string $jurisdictionCode,
        public string $authorityCode,
        public string $authorityIdentifier,
    ) {
    }
}
