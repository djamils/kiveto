<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetBackofficeProfile;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetBackofficeProfile implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
