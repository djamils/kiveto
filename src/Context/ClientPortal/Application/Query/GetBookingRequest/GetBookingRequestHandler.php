<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetBookingRequest;

use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ClinicBookingRequest;
use App\Context\ClientPortal\Application\Service\ClinicBookingRequests;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetBookingRequestHandler
{
    public function __construct(private ClinicBookingRequests $requests)
    {
    }

    public function __invoke(GetBookingRequest $query): ?ClinicBookingRequest
    {
        return $this->requests->one($query->clinicId, $query->requestId);
    }
}
