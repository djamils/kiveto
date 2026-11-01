<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function findById(AppointmentId $id): ?Appointment;

    /**
     * The appointment as last committed, its row locked until the end of the
     * transaction (`SELECT … FOR UPDATE`, a current read).
     */
    public function findByIdForUpdate(AppointmentId $id): ?Appointment;

    /**
     * The pending requests whose expiry has passed (`pending_expires_at <= now`).
     *
     * @return list<string> appointment ids
     */
    public function expiredPendingIds(\DateTimeImmutable $now): array;
}
