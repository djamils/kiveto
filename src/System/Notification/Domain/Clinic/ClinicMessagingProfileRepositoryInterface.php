<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Clinic;

interface ClinicMessagingProfileRepositoryInterface
{
    /** Creates or replaces the clinic's profile. */
    public function save(ClinicMessagingProfile $profile): void;

    public function find(string $clinicId): ?ClinicMessagingProfile;

    /**
     * Reads the profile and locks its row until the end of the current
     * transaction (`SELECT … FOR UPDATE`): the SMS cap check of a clinic is
     * serialised on it.
     */
    public function findForUpdate(string $clinicId): ?ClinicMessagingProfile;
}
