<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CancelAppointment;

use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);

        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (null === $appointment) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId
            ));
        }

        $now = $this->clock->now();

        // Guard: appointments already ended cannot be cancelled
        if ($appointment->timeSlot()->endsAtUtc() < $now) {
            throw new \DomainException('Impossible d\'annuler un rendez-vous passé.');
        }

        $appointment->cancel();
        $this->appointmentRepository->save($appointment);
    }
}
