<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

interface PetChangeRequestRepositoryInterface
{
    public function save(PetChangeRequest $request): void;

    public function findById(string $clinicId, string $requestId): ?PetChangeRequest;

    /** The request, locked until the transaction ends. */
    public function findByIdForUpdate(string $clinicId, string $requestId): ?PetChangeRequest;

    /** How many requests on that pet and field are still submitted. */
    public function countSubmitted(string $clinicId, string $petId, ChangeField $field): int;

    /**
     * The submitted requests hanging on a subject, locked until the
     * transaction ends; none for a subject a change request does not carry
     * (a prescription, an appointment). `clinicId` is ignored for a user.
     *
     * @return list<PetChangeRequest>
     */
    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array;

    /**
     * Every request of an owner account, any clinic, oldest first.
     *
     * @return list<PetChangeRequest>
     */
    public function findByUser(string $userId): array;
}
