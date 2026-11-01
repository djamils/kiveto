<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\Scheduling\Application\Query\IsOnlineBookingOpen\IsOnlineBookingOpen;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * 422 `booking_not_open` when the clinic takes no online booking (disabled,
 * or the clinic is not active), for the booking routes whose query does not
 * refuse it itself (motifs, practitioners).
 */
final readonly class OnlineBookingGate
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function require(string $clinicId): void
    {
        if (true !== $this->queryBus->ask(new IsOnlineBookingOpen($clinicId))) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN);
        }
    }
}
