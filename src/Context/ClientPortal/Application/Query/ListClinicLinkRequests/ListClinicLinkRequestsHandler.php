<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicLinkRequests;

use App\Context\ClientPortal\Application\Service\ClinicLinkRequests;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicLinkRequestsHandler
{
    public function __construct(private ClinicLinkRequests $requests)
    {
    }

    /**
     * @return list<ClinicLinkRequest>
     */
    public function __invoke(ListClinicLinkRequests $query): array
    {
        return $this->requests->ofClinic($query->clinicId);
    }
}
