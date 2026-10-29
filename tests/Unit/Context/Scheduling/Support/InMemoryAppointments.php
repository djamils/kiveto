<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;

final class InMemoryAppointments implements AppointmentRepositoryInterface
{
    /** @var array<string, Appointment> */
    private array $appointments = [];

    public function save(Appointment $appointment): void
    {
        $this->appointments[$appointment->id()->toString()] = $appointment;
    }

    public function findById(AppointmentId $id): ?Appointment
    {
        return $this->appointments[$id->toString()] ?? null;
    }
}
