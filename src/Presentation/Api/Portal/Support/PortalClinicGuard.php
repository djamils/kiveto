<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Query\HasConfirmedClientLink\HasConfirmedClientLink;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The gate of every `/clinics/{c}/…` route: without a confirmed link to the
 * clinic the answer is 404 `not_found`, never 403. It only opens the route;
 * the ClientPortal queries scope their rows to the caller's confirmed links
 * themselves.
 */
final readonly class PortalClinicGuard
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function require(string $userId, string $clinicId): void
    {
        if (true !== $this->queryBus->ask(new HasConfirmedClientLink($userId, $clinicId))) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }
    }
}
