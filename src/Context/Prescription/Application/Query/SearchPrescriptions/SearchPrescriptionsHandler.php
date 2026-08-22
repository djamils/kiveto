<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\SearchPrescriptions;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchPrescriptionsHandler
{
    public function __construct(private PrescriptionReadRepositoryInterface $prescriptions)
    {
    }

    public function __invoke(SearchPrescriptions $query): PrescriptionListItemView
    {
        $result = $this->prescriptions->search(ClinicId::fromString($query->clinicId), $query->criteria);

        return new PrescriptionListItemView(
            items: $result['items'],
            total: $result['total'],
            page: $query->criteria->page,
            perPage: $query->criteria->perPage,
        );
    }
}
