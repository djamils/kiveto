<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetMarketingAuthorizationDetail implements QueryInterface
{
    public function __construct(
        public string $marketingAuthorizationId,
    ) {
    }
}
