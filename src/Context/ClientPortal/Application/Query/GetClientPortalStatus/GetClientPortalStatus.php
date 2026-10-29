<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClientPortalStatus;

use App\Shared\Application\Bus\QueryInterface;

/** Where a clinic client stands with the Portal (client sheet). Answers ClientPortalStatus. */
final readonly class GetClientPortalStatus implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
    ) {
    }
}
