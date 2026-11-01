<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestSubject;

final class InMemoryChangeRequests implements PetChangeRequestRepositoryInterface
{
    /** @var array<string, PetChangeRequest> by id */
    public array $requests = [];

    public int $saves = 0;

    public function save(PetChangeRequest $request): void
    {
        $this->requests[$request->id()->toString()] = $request;
        ++$this->saves;
    }

    public function findById(string $clinicId, string $requestId): ?PetChangeRequest
    {
        $request = $this->requests[$requestId] ?? null;

        return $request?->clinicId() === $clinicId ? $request : null;
    }

    public function findByIdForUpdate(string $clinicId, string $requestId): ?PetChangeRequest
    {
        return $this->findById($clinicId, $requestId);
    }

    public function countSubmitted(string $clinicId, string $petId, ChangeField $field): int
    {
        return \count(array_filter(
            $this->requests,
            static fn (PetChangeRequest $r): bool => $r->isSubmitted() && $r->clinicId() === $clinicId && $r->petId() === $petId && $r->change()->field() === $field,
        ));
    }

    public function findSubmittedForSubjectForUpdate(RequestSubject $subject, string $subjectId, ?string $clinicId): array
    {
        return array_values(array_filter($this->requests, static fn (PetChangeRequest $r): bool => $r->isSubmitted() && match ($subject) {
            RequestSubject::CLIENT => $r->clientId() === $subjectId && $r->clinicId() === $clinicId,
            RequestSubject::USER   => $r->userId() === $subjectId,
            RequestSubject::PET    => $r->petId() === $subjectId && $r->clinicId() === $clinicId,
            default                => false,
        }));
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->requests, static fn (PetChangeRequest $r): bool => $r->userId() === $userId));
    }
}
