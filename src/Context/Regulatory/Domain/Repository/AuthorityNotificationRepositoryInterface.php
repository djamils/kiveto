<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Repository;

use App\Context\Regulatory\Domain\AuthorityNotification;

interface AuthorityNotificationRepositoryInterface
{
    public function save(AuthorityNotification $notification): void;

    public function findByAdmissionId(string $admissionId): ?AuthorityNotification;
}
