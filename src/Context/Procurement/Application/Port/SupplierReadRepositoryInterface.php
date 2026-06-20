<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface SupplierReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit): array;
}
