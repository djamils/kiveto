<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface SupplierPricingReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findByClinic(string $clinicId): array;

    /** @return array<string, mixed>|null */
    public function findByClinicAndEntry(string $clinicId, string $entryId): ?array;
}
