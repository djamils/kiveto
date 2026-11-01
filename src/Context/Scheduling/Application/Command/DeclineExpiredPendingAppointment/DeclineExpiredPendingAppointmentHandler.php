<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment;

use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Re-checks under the practitioner-day lock and the row lock that the
 * request is still pending and expired — confirmed, declined, withdrawn or
 * moved later meanwhile, it is left alone — then declines it as `expired`:
 * the slot is free and the client is told once (APPOINTMENT_DECLINED).
 */
#[AsMessageHandler]
final readonly class DeclineExpiredPendingAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(DeclineExpiredPendingAppointment $command): void
    {
        $appointment = $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId));
        $expiresAt   = $appointment?->pendingExpiresAt();

        if (null === $appointment || !$appointment->status()->isPending() || null === $expiresAt || $expiresAt > $this->clock->now()) {
            return;
        }

        $appointment->decline(DeclineReason::EXPIRED);
        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
