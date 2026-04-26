<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Repository;

use App\Context\Regulatory\Domain\MairieNotification;

interface MairieNotificationRepositoryInterface
{
    public function save(MairieNotification $notification): void;

    public function findByAdmissionId(string $admissionId): ?MairieNotification;
}
