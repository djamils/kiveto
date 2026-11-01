<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\NewClientRequestException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
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
 * client's request (portal, no owner yet) is resolved from « Demandes
 * Portal » only (`NewClientRequestException`, nothing changes).
 */
#[AsMessageHandler]
final readonly class ConfirmAppointmentHandler
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
     * @throws PendingAppointmentExpiredException when its expiry has passed
     * @throws NewClientRequestException          when a new client's request waits for its client file
     */
    public function __invoke(ConfirmAppointment $command): void
    {
        $appointment = $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId), ClinicId::fromString($command->clinicId))
            ?? throw new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $command->appointmentId));

        if (!$appointment->status()->isPending()) {
            throw new AppointmentAlreadyHandledException();
        }

        if (AppointmentSource::PORTAL === $appointment->source() && null === $appointment->ownerId()) {
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
