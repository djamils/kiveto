<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface SupplierAccountReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findByClinic(string $clinicId): array;

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array;
}
