<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;

/**
 * `add()` refuses a second open request on a prescription, like the
 * unique open key; `concurrentWinner` simulates a request committed by a
 * concurrent submit between the snapshot read and the insert.
 */
final class InMemoryRenewalRequests implements PrescriptionRenewalRequestRepositoryInterface
{
    /** @var array<string, PrescriptionRenewalRequest> by id, in insertion order */
    public array $requests = [];

    public ?PrescriptionRenewalRequest $concurrentWinner = null;

    public int $saves = 0;

    public function add(PrescriptionRenewalRequest $request): bool
    {
        if (null !== $this->concurrentWinner) {
            $this->requests[$this->concurrentWinner->id()->toString()] = $this->concurrentWinner;
            $this->concurrentWinner                                    = null;
        }

        if (null !== $this->open($request->clinicId(), $request->prescriptionId())) {
            return false;
        }

        $this->requests[$request->id()->toString()] = $request;

        return true;
    }

    public function save(PrescriptionRenewalRequest $request): void
    {
        $this->requests[$request->id()->toString()] = $request;
        ++$this->saves;
    }

    public function findById(string $clinicId, string $requestId): ?PrescriptionRenewalRequest
    {
        $request = $this->requests[$requestId] ?? null;

        return $request?->clinicId() === $clinicId ? $request : null;
    }

    public function findByIdForUpdate(string $clinicId, string $requestId): ?PrescriptionRenewalRequest
    {
        return $this->findById($clinicId, $requestId);
    }

    public function findSubmittedForPrescription(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        return $this->open($clinicId, $prescriptionId);
    }

    public function findSubmittedForPrescriptionForUpdate(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        return $this->open($clinicId, $prescriptionId);
    }

    public function findLatestRejected(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        $latest = null;

        foreach ($this->requests as $request) {
            if (RequestStatus::REJECTED === $request->status() && $request->clinicId() === $clinicId && $request->prescriptionId() === $prescriptionId) {
                $latest = $request;
            }
        }

        return $latest;
    }

    public function latestByPrescriptions(string $clinicId, array $prescriptionIds): array
    {
        $latest = [];

        foreach ($this->requests as $request) {
            if ($request->clinicId() === $clinicId && \in_array($request->prescriptionId(), $prescriptionIds, true)) {
                $latest[$request->prescriptionId()] = $request;
            }
        }

        return $latest;
    }

    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array
    {
        return array_values(array_filter($this->requests, static fn (PrescriptionRenewalRequest $r): bool => $r->isSubmitted() && match ($subject) {
            RequestSubject::CLIENT       => $r->clientId() === $subjectId && $r->clinicId() === $clinicId,
            RequestSubject::USER         => $r->userId() === $subjectId,
            RequestSubject::PRESCRIPTION => $r->prescriptionId() === $subjectId && $r->clinicId() === $clinicId,
            RequestSubject::PET          => $r->petId() === $subjectId && $r->clinicId() === $clinicId,
            default                      => false,
        }));
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->requests, static fn (PrescriptionRenewalRequest $r): bool => $r->userId() === $userId));
    }

    private function open(string $clinicId, string $prescriptionId): ?PrescriptionRenewalRequest
    {
        foreach ($this->requests as $request) {
            if ($request->isSubmitted() && $request->clinicId() === $clinicId && $request->prescriptionId() === $prescriptionId) {
                return $request;
            }
        }

        return null;
    }
}
