<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface AdmissionReadRepositoryInterface
{
    /**
     * @return list<WaitingRoomItemDto>
     */
    public function findActiveInWaitingRoom(string $clinicId): array;

    /**
     * Returns all active admissions for the clinic, regardless of location status.
     * Used by the Flux du jour view to populate all kanban columns.
     *
     * @return list<WaitingRoomItemDto>
     */
    public function findAllActiveForClinic(string $clinicId): array;

    /**
     * Admissions closed since a point in time, most recent first.
     *
     * Feeds the "Sortie" column of the Flux du jour view, which shows the
     * patients discharged today.
     *
     * @return list<WaitingRoomItemDto>
     */
    public function findClosedForClinicSince(string $clinicId, \DateTimeImmutable $since): array;

    public function getAdmissionContext(string $admissionId): AdmissionContextDto;
}
