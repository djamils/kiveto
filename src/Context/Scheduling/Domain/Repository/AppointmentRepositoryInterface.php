<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;

interface AppointmentRepositoryInterface
{
    public function save(Appointment $appointment): void;

    public function findById(AppointmentId $id): ?Appointment;
}
