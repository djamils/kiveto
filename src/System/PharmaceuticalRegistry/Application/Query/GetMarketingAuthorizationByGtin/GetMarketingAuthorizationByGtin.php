<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationByGtin;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetMarketingAuthorizationByGtin implements QueryInterface
{
    public function __construct(
        public string $gtin,
    ) {
    }
}
