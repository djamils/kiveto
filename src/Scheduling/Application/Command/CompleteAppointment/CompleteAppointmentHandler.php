<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CompleteAppointment;

use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CompleteAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {
    }

    public function __invoke(CompleteAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);

        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (null === $appointment) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId
            ));
        }

        $appointment->complete();
        $this->appointmentRepository->save($appointment);
    }
}
