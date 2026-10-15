<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\MarkAppointmentNoShow;

use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkAppointmentNoShowHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkAppointmentNoShow $command): void
    {
        $appointment = $this->appointmentRepository->findById(AppointmentId::fromString($command->appointmentId));
        // An appointment of another clinic is reported as missing, never exposed
        if (null === $appointment || !$appointment->clinicId()->equals(ClinicId::fromString($command->clinicId))) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId,
            ));
        }

        // Guard: nobody can be « absent » from a visit that has not begun yet
        if ($appointment->timeSlot()->startsAtUtc() > $this->clock->now()) {
            throw new \DomainException('Impossible de marquer non venu un rendez-vous à venir.');
        }

        $appointment->markNoShow();
        $this->appointmentRepository->save($appointment);
    }
}
