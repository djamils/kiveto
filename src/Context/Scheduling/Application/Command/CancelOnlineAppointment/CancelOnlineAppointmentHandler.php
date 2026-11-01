<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CancelOnlineAppointment;

use App\Context\Scheduling\Application\Service\OnlineCancellationWindow;
use App\Context\Scheduling\Application\Service\OnlineChangeNotice;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * S4f: a client cancels within the cancellation window; the slot is freed,
 * the usual cancellation message follows (receipt) and the practitioner's
 * bell says « RDV annulé en ligne ». A pending request is withdrawn.
 * Naturally idempotent: an appointment already cancelled by the client is
 * answered as is.
 *
 * Refusals: `OnlineBookingNotFoundException` (unknown or of another
 * clinic), `AppointmentNotModifiableException` (declined, completed, no-show
 * or cancelled by the clinic), `CancellationWindowClosedException`.
 */
#[AsMessageHandler]
final readonly class CancelOnlineAppointmentHandler
{
    public function __construct(
        private PendingAppointmentLoader $loader,
        private AppointmentRepositoryInterface $appointments,
        private OnlineBookingSettingsRepositoryInterface $settings,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
        private OnlineChangeNotice $notice,
    ) {
    }

    public function __invoke(CancelOnlineAppointment $command): string
    {
        $reason = null === $command->cancellationReason ? null : CancellationReason::tryFrom($command->cancellationReason);

        if (null !== $command->cancellationReason && null === $reason) {
            throw new \InvalidArgumentException(\sprintf('Unknown cancellation reason "%s".', $command->cancellationReason));
        }

        $clinicId = ClinicId::fromString($command->clinicId);

        // Locks the slot's days, then reads the row for update: a concurrent confirm or decline is seen
        $appointment = Uuid::isValid($command->appointmentId)
            ? $this->loader->lockAndLoad(AppointmentId::fromString($command->appointmentId), $clinicId)
            : null;

        if (null === $appointment) {
            throw OnlineBookingNotFoundException::appointment($command->appointmentId);
        }

        if (AppointmentStatus::CANCELLED === $appointment->status() && CancellationActor::CLIENT === $appointment->cancelledBy()) {
            return $command->appointmentId;
        }

        if ($appointment->status()->isTerminal()) {
            throw new AppointmentNotModifiableException();
        }

        OnlineCancellationWindow::assertOpen($appointment, $this->settings->forClinic($clinicId), $this->clock->now());

        $appointment->cancel(CancellationActor::CLIENT, $reason);

        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);

        $this->notice->cancelled($appointment);

        return $command->appointmentId;
    }
}
