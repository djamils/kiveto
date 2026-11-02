<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetClinicLinkDecision;

use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Service\ClinicLinkRequests;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicLinkDecisionHandler
{
    public function __construct(private ClinicLinkRequests $requests)
    {
    }

    public function __invoke(GetClinicLinkDecision $query): ?ClinicLinkRequest
    {
        return $this->requests->decision($query->clinicId, $query->decisionId);
    }
}
