<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\Port;

interface RegulatoryTasksReadRepositoryInterface
{
    /**
     * Returns pending Mairie notifications for a given clinic.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingMairieNotifications(string $clinicId): array;

    /**
     * Returns active stray custodies for a given clinic.
     *
     * @return list<array<string, mixed>>
     */
    public function findActiveStrayCustodies(string $clinicId): array;
}
