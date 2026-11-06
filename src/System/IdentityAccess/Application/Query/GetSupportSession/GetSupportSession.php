<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetSupportSession;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetSupportSession implements QueryInterface
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
