<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CheckInAppointment;

use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A pending request completed here is confirmed silently first (the client
 * is on site): its events are published, so the notification sync marks the
 * slot confirmed without a message, the « à confirmer » bell entry goes and
 * a portal booking is announced confirmed (AC 140).
 */
#[AsMessageHandler]
final readonly class CheckInAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private DomainEventPublisher $domainEventPublisher,
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

        $this->domainEventPublisher->publish($appointment);
    }
}
