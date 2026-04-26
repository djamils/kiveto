<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface AdmissionReadRepositoryInterface
{
    /**
     * @return list<WaitingRoomItemDto>
     */
    public function findActiveInWaitingRoom(string $clinicId): array;

    public function getAdmissionContext(string $admissionId): AdmissionContextDto;
}
