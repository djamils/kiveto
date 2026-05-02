<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CheckInAppointment;

use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckInAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    public function __invoke(CheckInAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $clinicId      = ClinicId::fromString($command->clinicId);

        $appointment = $this->appointmentRepository->findById($appointmentId);

        if (null === $appointment) {
            return;
        }

        if (!$appointment->clinicId()->equals($clinicId)) {
            return;
        }

        $appointment->linkAdmission($command->admissionId);
        $appointment->complete();

        $this->appointmentRepository->save($appointment);
    }
}
