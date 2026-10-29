<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetPortalAccount;

use App\Shared\Application\Bus\QueryInterface;

/** The owner account behind a Portal session; answers a PortalAccount or null. */
final readonly class GetPortalAccount implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
