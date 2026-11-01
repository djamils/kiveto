<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\WithdrawPendingOnlineAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * A client cancel of the pending request, under the practitioner-day and
 * row locks, without the cancellation window: the request was never
 * confirmed, so the client is told nothing. Idempotent.
 */
#[AsMessageHandler]
final readonly class WithdrawPendingOnlineAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(WithdrawPendingOnlineAppointment $command): void
    {
        $appointment = Uuid::isValid($command->appointmentId)
            ? $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId), ClinicId::fromString($command->clinicId))
            : null;

        if (null === $appointment || !$appointment->status()->isPending()) {
            return;
        }

        $appointment->cancel(CancellationActor::CLIENT);
        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
