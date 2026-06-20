<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface PurchaseOrderReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findByClinic(string $clinicId): array;

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByExternalReference(string $externalRef): ?array;

    /**
     * Returns POs with status PARTIALLY_RECEIVED that have been submitted more than $daysThreshold days ago.
     *
     * @return list<array<string, mixed>>
     */
    public function findStale(int $daysThreshold): array;
}
