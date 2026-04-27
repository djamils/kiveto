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

    public function getAdmissionContext(string $admissionId): AdmissionContextDto;
}
