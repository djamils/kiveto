<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClinicLinkRequest;

use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Service\ClinicLinkRequests;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicLinkRequestHandler
{
    public function __construct(private ClinicLinkRequests $requests)
    {
    }

    public function __invoke(GetClinicLinkRequest $query): ?ClinicLinkRequest
    {
        return $this->requests->ofAccount($query->clinicId, $query->userId);
    }
}
