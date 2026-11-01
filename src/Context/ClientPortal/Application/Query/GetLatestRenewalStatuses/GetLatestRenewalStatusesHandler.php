<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses;

use App\Context\ClientPortal\Application\Service\RenewalStatuses;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetLatestRenewalStatusesHandler
{
    public function __construct(private RenewalStatuses $statuses)
    {
    }

    /**
     * @return array<string, RenewalStatus>
     */
    public function __invoke(GetLatestRenewalStatuses $query): array
    {
        return $this->statuses->of($query->clinicId, $query->prescriptionIds);
    }
}
