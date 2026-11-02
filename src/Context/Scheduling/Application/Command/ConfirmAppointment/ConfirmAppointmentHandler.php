<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmAppointment;

use App\Context\Scheduling\Application\Port\NewClientConfirmationInterface;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\NewClientRequestException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Under the practitioner-day lock and the row lock: a request that is no
 * longer pending was handled first (« Ce RDV a déjà été traité. »); one past
 * its expiry is refused (« Demande expirée. ») — the scan declines it.
 * Otherwise it becomes PLANNED: the client gets the usual confirmation and
 * the practitioner's « à confirmer » bell entry is withdrawn. A new
 * client's request (portal, no owner yet) is confirmed through ClientPortal
 * (`NewClientConfirmationInterface`, S7-01: client file, animal and Portal
 * link first), before any lock is taken here; when no pending booking
 * request holds it, it stays refused (`NewClientRequestException`).
 */
#[AsMessageHandler]
final readonly class ConfirmAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
        private NewClientConfirmationInterface $newClients,
    ) {
    }

    /**
     * @throws \InvalidArgumentException          when the appointment does not exist in the clinic
     * @throws AppointmentAlreadyHandledException when it is no longer pending
     * @throws PendingAppointmentExpiredException when its expiry has passed
     * @throws NewClientRequestException          when a new client's request has no pending booking request
     */
    public function __invoke(ConfirmAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $clinicId      = ClinicId::fromString($command->clinicId);
        $read          = $this->appointments->findById($appointmentId);

        // A plain read: ClientPortal locks its request before Scheduling's locks
        $newClientRequest = null !== $read && $read->clinicId()->equals($clinicId) && $read->isNewClientRequest();

        if ($newClientRequest && $this->newClients->confirm($command->clinicId, $command->appointmentId, $read->animalId(), silent: false)) {
            return;
        }

        $appointment = $this->loader->lockAndLoad($appointmentId, $clinicId)
            ?? throw new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $command->appointmentId));

        if (!$appointment->status()->isPending()) {
            throw new AppointmentAlreadyHandledException();
        }

        if ($appointment->isNewClientRequest()) {
            throw new NewClientRequestException();
        }

        $expiresAt = $appointment->pendingExpiresAt();

        if (null !== $expiresAt && $expiresAt <= $this->clock->now()) {
            throw new PendingAppointmentExpiredException();
        }

        $appointment->confirm();
        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
