<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetSupplierPricingForClinic;

use App\Context\Procurement\Application\Port\SupplierPricingReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSupplierPricingForClinicHandler
{
    public function __construct(
        private SupplierPricingReadRepositoryInterface $readRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(GetSupplierPricingForClinic $query): array
    {
        return $this->readRepository->findByClinic($query->clinicId);
    }
}
