<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Adapter\Scheduling;

use App\Context\ClinicListing\Application\Port\OnlineBookingStatusInterface;
use App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients\ListClinicsOpenToNewClients;
use App\Shared\Application\Bus\QueryBusInterface;

/** Scheduling's ListClinicsOpenToNewClients, one query per page. */
final readonly class QueryBusOnlineBookingStatus implements OnlineBookingStatusInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function openToNewClients(array $clinicIds): array
    {
        $open = $this->queryBus->ask(new ListClinicsOpenToNewClients($clinicIds));

        return \is_array($open) ? array_values(array_filter($open, \is_string(...))) : [];
    }
}
