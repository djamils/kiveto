<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CancelAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A clinic cancel of a pending request is a decline (domain rule): like a
 * « Refuser » click, it takes the practitioner-day lock and reads the row
 * `FOR UPDATE`, so it races a confirmation, a decline or the expiry with
 * exactly one winner — a request handled meanwhile is refused with « Ce RDV
 * a déjà été traité. ».
 */
#[AsMessageHandler]
final readonly class CancelAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private PendingAppointmentLoader $loader,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @throws \InvalidArgumentException          when the appointment does not exist in the clinic
     * @throws AppointmentAlreadyHandledException when the clinic cancels a request handled meanwhile
     */
    public function __invoke(CancelAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $actor         = CancellationActor::from($command->cancelledBy);

        $appointment = $this->appointmentRepository->findById($appointmentId);
        // An appointment of another clinic is reported as missing, never exposed
        if (null === $appointment || !$appointment->clinicId()->equals(ClinicId::fromString($command->clinicId))) {
            throw $this->missing($command->appointmentId);
        }

        if ($appointment->status()->isPending()) {
            $appointment = $this->loader->lockAndReload($appointment) ?? throw $this->missing($command->appointmentId);

            // Confirmed, declined or expired meanwhile: the clinic's decline lost the race
            if (CancellationActor::CLINIC === $actor && !$appointment->status()->isPending()) {
                throw new AppointmentAlreadyHandledException();
            }
        }

        $now = $this->clock->now();

        // Guard: appointments already ended cannot be cancelled
        if ($appointment->timeSlot()->endsAtUtc() < $now) {
            throw new \DomainException('Impossible d\'annuler un rendez-vous passé.');
        }

        $appointment->cancel(
            $actor,
            null === $command->cancellationReason ? null : CancellationReason::from($command->cancellationReason),
            $command->notifyClient,
        );
        $this->appointmentRepository->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }

    private function missing(string $appointmentId): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $appointmentId));
    }
}
