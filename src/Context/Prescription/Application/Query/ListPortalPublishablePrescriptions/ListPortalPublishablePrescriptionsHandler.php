<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPortalPublishablePrescriptionsHandler
{
    public function __construct(private PrescriptionReadRepositoryInterface $prescriptions)
    {
    }

    /**
     * @return list<PortalPublishablePrescription>
     */
    public function __invoke(ListPortalPublishablePrescriptions $query): array
    {
        if ($query->limit < 1) {
            return [];
        }

        return $this->prescriptions->portalPublishable($query->issuedFrom, $query->afterId, $query->limit);
    }
}
