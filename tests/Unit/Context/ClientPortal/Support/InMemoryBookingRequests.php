<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestRepositoryInterface;

/** Booking requests in memory; records which reads were `FOR UPDATE` and which accounts were scrubbed. */
final class InMemoryBookingRequests implements BookingRequestRepositoryInterface
{
    /** @var array<string, BookingRequest> */
    public array $requests = [];

    /** @var list<string> */
    public array $lockedForUpdate = [];

    /** @var list<string> */
    public array $scrubbed = [];

    public function save(BookingRequest $request): void
    {
        $this->requests[$request->id()->toString()] = $request;
    }

    public function findInClinic(string $clinicId, string $requestId): ?BookingRequest
    {
        $request = $this->requests[$requestId] ?? null;

        return null !== $request && $request->clinicId() === $clinicId ? $request : null;
    }

    public function findOfUser(string $userId, string $requestId): ?BookingRequest
    {
        $request = $this->requests[$requestId] ?? null;

        return null !== $request && $request->userId() === $userId ? $request : null;
    }

    public function findInClinicForUpdate(string $clinicId, string $requestId): ?BookingRequest
    {
        $this->lockedForUpdate[] = $requestId;

        return $this->findInClinic($clinicId, $requestId);
    }

    public function findByAppointmentForUpdate(string $appointmentId): ?BookingRequest
    {
        foreach ($this->requests as $request) {
            if ($request->appointmentId() === $appointmentId) {
                $this->lockedForUpdate[] = $request->id()->toString();

                return $request;
            }
        }

        return null;
    }

    public function findPendingByUserForUpdate(string $userId): array
    {
        $this->lockedForUpdate[] = 'user:' . $userId;

        return array_values(array_filter($this->ordered(), static fn (BookingRequest $r): bool => $r->userId() === $userId && $r->isPending()));
    }

    public function findPageByUser(string $userId, ?\DateTimeImmutable $afterCreatedAt, ?string $afterId, int $limit): array
    {
        $rows = array_reverse(array_values(array_filter($this->ordered(), static fn (BookingRequest $r): bool => $r->userId() === $userId)));

        if (null !== $afterCreatedAt && null !== $afterId) {
            $rows = array_values(array_filter($rows, static fn (BookingRequest $r): bool => [$r->createdAt(), $r->id()->toString()] < [$afterCreatedAt, $afterId]));
        }

        return \array_slice($rows, 0, $limit);
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->ordered(), static fn (BookingRequest $r): bool => $r->userId() === $userId));
    }

    public function findPendingByClinic(string $clinicId): array
    {
        return array_values(array_filter($this->ordered(), static fn (BookingRequest $r): bool => $r->clinicId() === $clinicId && $r->isPending()));
    }

    public function scrubUser(string $userId): void
    {
        $this->scrubbed[] = $userId;

        foreach ($this->requests as $id => $r) {
            if ($r->userId() !== $userId) {
                continue;
            }

            $this->requests[$id] = BookingRequest::reconstitute(
                $r->id(),
                $r->userId(),
                $r->clinicId(),
                $r->listingId(),
                $r->appointmentId(),
                $r->motifLabel(),
                $r->startAt(),
                null,
                null,
                null,
                null,
                null,
                $r->status(),
                $r->declineReason(),
                $r->clientId(),
                $r->createdAt(),
                $r->resolvedAt(),
            );
        }
    }

    /**
     * Oldest first.
     *
     * @return list<BookingRequest>
     */
    private function ordered(): array
    {
        $rows = array_values($this->requests);
        usort($rows, static fn (BookingRequest $a, BookingRequest $b): int => [$a->createdAt(), $a->id()->toString()] <=> [$b->createdAt(), $b->id()->toString()]);

        return $rows;
    }
}
