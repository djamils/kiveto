<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListSupplierAccountsByClinic;

use App\Context\Procurement\Application\Port\SupplierAccountReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSupplierAccountsByClinicHandler
{
    public function __construct(
        private SupplierAccountReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(ListSupplierAccountsByClinic $query): array
    {
        return $this->readRepository->findByClinic($query->clinicId);
    }
}
