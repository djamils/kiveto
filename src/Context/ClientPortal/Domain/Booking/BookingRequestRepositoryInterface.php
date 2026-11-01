<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

interface BookingRequestRepositoryInterface
{
    public function save(BookingRequest $request): void;

    /** Any request of the clinic; null for an unknown or malformed id, or another clinic's. */
    public function findInClinic(string $clinicId, string $requestId): ?BookingRequest;

    /** A request of the account; null for an unknown or malformed id, or another account's. */
    public function findOfUser(string $userId, string $requestId): ?BookingRequest;

    /** The same, read `FOR UPDATE`. */
    public function findInClinicForUpdate(string $clinicId, string $requestId): ?BookingRequest;

    /** The request that holds this appointment, read `FOR UPDATE`; null when the appointment is no new-client booking. */
    public function findByAppointmentForUpdate(string $appointmentId): ?BookingRequest;

    /**
     * The account's pending requests, any clinic, read `FOR UPDATE` (the
     * limits of S7d are counted under these locks), oldest first.
     *
     * @return list<BookingRequest>
     */
    public function findPendingByUserForUpdate(string $userId): array;

    /**
     * The account's requests, newest first (`created_at`, `id`), after the
     * position when given.
     *
     * @return list<BookingRequest>
     */
    public function findPageByUser(string $userId, ?\DateTimeImmutable $afterCreatedAt, ?string $afterId, int $limit): array;

    /**
     * Every request of the account, any status (data export), oldest first.
     *
     * @return list<BookingRequest>
     */
    public function findByUser(string $userId): array;

    /**
     * The clinic's pending requests, oldest first.
     *
     * @return list<BookingRequest>
     */
    public function findPendingByClinic(string $clinicId): array;

    /** Account deletion: the requester and new-pet snapshots of every request of the account set to NULL. */
    public function scrubUser(string $userId): void;
}
