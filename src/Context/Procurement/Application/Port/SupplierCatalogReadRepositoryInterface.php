<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface SupplierCatalogReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $supplierId): array;

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array;

    /** @return array<string, mixed>|null */
    public function findBySupplierAndCode(string $supplierId, string $code): ?array;
}
