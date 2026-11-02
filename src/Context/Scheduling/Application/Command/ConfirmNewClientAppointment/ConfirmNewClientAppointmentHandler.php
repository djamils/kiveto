<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Same locks and refusals as ConfirmAppointment; the owner and the animal
 * are set first (`assignNewClient`), so the confirmation and its message go
 * to the client file chosen. A silent one (the client is on site) ignores
 * the expiry, like `SilentPendingConfirmation`, and sends no message.
 */
#[AsMessageHandler]
final readonly class ConfirmNewClientAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @throws \InvalidArgumentException          when the appointment does not exist in the clinic
     * @throws AppointmentAlreadyHandledException when it is no longer pending
     * @throws PendingAppointmentExpiredException when its expiry has passed (not silent)
     */
    public function __invoke(ConfirmNewClientAppointment $command): void
    {
        $appointment = $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId), ClinicId::fromString($command->clinicId))
            ?? throw new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $command->appointmentId));

        if (!$appointment->status()->isPending()) {
            throw new AppointmentAlreadyHandledException();
        }

        $expiresAt = $appointment->pendingExpiresAt();

        if (!$command->silent && null !== $expiresAt && $expiresAt <= $this->clock->now()) {
            throw new PendingAppointmentExpiredException();
        }

        $appointment->assignNewClient($command->ownerId, $command->animalId);
        $appointment->confirm($command->silent);
        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
