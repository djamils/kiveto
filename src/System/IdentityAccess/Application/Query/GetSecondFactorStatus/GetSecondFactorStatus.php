<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetSecondFactorStatus;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetSecondFactorStatus implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
