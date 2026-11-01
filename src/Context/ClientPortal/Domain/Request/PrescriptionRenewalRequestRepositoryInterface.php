<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

interface PrescriptionRenewalRequestRepositoryInterface
{
    /**
     * Inserts a new request, unless another one on the same prescription is
     * already submitted (unique key on the stored `open_key`): a concurrent
     * insert waits for the first transaction, then finds it.
     *
     * @return bool false when an open request already exists (nothing written)
     */
    public function add(PrescriptionRenewalRequest $request): bool;

    /** Writes the changes of an existing request. */
    public function save(PrescriptionRenewalRequest $request): void;

    public function findById(string $clinicId, string $requestId): ?PrescriptionRenewalRequest;

    /** The request, locked until the transaction ends. */
    public function findByIdForUpdate(string $clinicId, string $requestId): ?PrescriptionRenewalRequest;

    /** The submitted request on the prescription, if any, as the transaction's snapshot sees it. */
    public function findSubmittedForPrescription(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest;

    /** The submitted request on the prescription, if any, locked (latest committed state). */
    public function findSubmittedForPrescriptionForUpdate(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest;

    /** The latest refused request on the prescription, if any. */
    public function findLatestRejected(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest;

    /**
     * The latest request (by submit) of each listed prescription, keyed by
     * prescription id; a prescription without request is left out.
     *
     * @param list<string> $prescriptionIds
     *
     * @return array<string, PrescriptionRenewalRequest>
     */
    public function latestByPrescriptions(string $clinicId, array $prescriptionIds): array;

    /**
     * The submitted requests hanging on a subject, locked until the
     * transaction ends; none for an appointment. `clinicId` is ignored for
     * a user.
     *
     * @return list<PrescriptionRenewalRequest>
     */
    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array;

    /**
     * Every request of an owner account, any clinic, oldest first.
     *
     * @return list<PrescriptionRenewalRequest>
     */
    public function findByUser(string $userId): array;
}
