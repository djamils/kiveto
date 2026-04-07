<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Repository;

use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\ValueObject\AppointmentId;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function findById(AppointmentId $id): ?Appointment;
}
