<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

use App\Context\ClientPortal\Application\Service\ClinicBookingRequests;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicBookingRequestsHandler
{
    public function __construct(private ClinicBookingRequests $requests)
    {
    }

    /**
     * @return list<ClinicBookingRequest>
     */
    public function __invoke(ListClinicBookingRequests $query): array
    {
        return $query->decided ? $this->requests->decidedOf($query->clinicId) : $this->requests->pendingOf($query->clinicId);
    }
}
