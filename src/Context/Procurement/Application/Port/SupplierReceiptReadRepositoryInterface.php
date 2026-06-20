<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

interface SupplierReceiptReadRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findByClinic(string $clinicId): array;

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array;

    /**
     * Returns PENDING_REVIEW receipts for a clinic.
     *
     * @return list<array<string, mixed>>
     */
    public function findPending(string $clinicId): array;

    /**
     * Queries procurement__unmatched_deliveries for unresolved entries.
     *
     * @return list<array<string, mixed>>
     */
    public function findUnmatched(string $clinicId): array;

    /**
     * Idempotence check: returns true if a receipt with this supplier+delivery_note_reference already exists.
     */
    public function existsBySupplierAndDeliveryNote(string $supplierId, string $deliveryNoteReference): bool;
}
