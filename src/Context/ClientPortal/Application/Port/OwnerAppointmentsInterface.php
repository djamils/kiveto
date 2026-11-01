<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;

/** The appointments of the owner's animals (Scheduling context). */
interface OwnerAppointmentsInterface
{
    /**
     * The earliest planned appointment of one of the animals starting at or
     * after now, or null.
     *
     * @param list<string> $animalIds
     */
    public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment;

    /**
     * One page of the appointments of the clients or of the animals; never
     * their free-text reason or notes.
     *
     * @param list<string> $ownerIds  client ids
     * @param list<string> $animalIds
     *
     * @throws InvalidCursorException
     *
     * @return CursorPage<OwnerAppointment>
     */
    public function page(string $clinicId, array $ownerIds, array $animalIds, AppointmentScope $scope, ?string $cursor, ?int $limit, \DateTimeImmutable $nowUtc): CursorPage;

    /** An appointment of the clinic, whoever it belongs to, or null when unknown. */
    public function find(string $clinicId, string $appointmentId): ?OwnerAppointment;

    /**
     * The appointments of the animals holding their slot from `fromUtc` on
     * whose motif carries a preventive care kind.
     *
     * @param list<string> $animalIds
     *
     * @return list<CareBooking>
     */
    public function careBookings(string $clinicId, array $animalIds, \DateTimeImmutable $fromUtc): array;
}
