<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently;

use App\Context\Scheduling\Application\Service\SilentPendingConfirmation;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Publishes `AppointmentConfirmed{silent: true}` of a pending request: the
 * notification sync marks the slot as told without a message, the « à
 * confirmer » bell entry goes and a portal booking is announced confirmed
 * (`PendingAppointmentConfirmedIntegrationEvent`).
 */
#[AsMessageHandler]
final readonly class ConfirmPendingAppointmentSilentlyHandler
{
    public function __construct(
        private SilentPendingConfirmation $silentConfirmation,
        private AppointmentRepositoryInterface $appointments,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the appointment does not exist (in the clinic, when given)
     */
    public function __invoke(ConfirmPendingAppointmentSilently $command): void
    {
        $appointment = $this->silentConfirmation->load(
            AppointmentId::fromString($command->appointmentId),
            null === $command->clinicId ? null : ClinicId::fromString($command->clinicId),
        ) ?? throw new \InvalidArgumentException(\sprintf('Appointment with ID "%s" does not exist.', $command->appointmentId));

        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
