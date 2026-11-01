<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeclineAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Under the practitioner-day lock and the row lock: a request that is no
 * longer pending was handled first (« Ce RDV a déjà été traité. »).
 * Otherwise it becomes DECLINED (reason `declined`): the slot is free, the
 * client is told once (APPOINTMENT_DECLINED) and the practitioner's
 * « à confirmer » bell entry is withdrawn. A request past its expiry may
 * still be declined.
 */
#[AsMessageHandler]
final readonly class DeclineAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @throws \InvalidArgumentException          when the appointment does not exist in the clinic
     * @throws AppointmentAlreadyHandledException when it is no longer pending
     */
    public function __invoke(DeclineAppointment $command): void
    {
        $appointment = $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId), ClinicId::fromString($command->clinicId))
            ?? throw new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $command->appointmentId));

        if (!$appointment->status()->isPending()) {
            throw new AppointmentAlreadyHandledException();
        }

        $appointment->decline(DeclineReason::DECLINED);
        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
