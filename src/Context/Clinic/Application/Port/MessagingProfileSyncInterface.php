<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Clinic is the source of truth of what Notification needs about a clinic;
 * it pushes it there (Notification never reads Clinic).
 */
interface MessagingProfileSyncInterface
{
    public function pushProfile(ClinicMessagingProfileData $profile): void;

    /**
     * @param string $day Y-m-d (Europe/Paris)
     */
    public function pushVeterinarianCount(string $clinicId, string $day, int $count): void;
}
