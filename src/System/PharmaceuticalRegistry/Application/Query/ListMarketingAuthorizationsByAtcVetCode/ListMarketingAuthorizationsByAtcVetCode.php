<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByAtcVetCode;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListMarketingAuthorizationsByAtcVetCode implements QueryInterface
{
    public function __construct(
        public string $atcVetCode,
    ) {
    }
}
