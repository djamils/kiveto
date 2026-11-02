<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CompleteAppointment;

use App\Context\Scheduling\Application\Service\SilentPendingConfirmation;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A pending request completed here is confirmed silently first, under the
 * practitioner-day lock (`SilentPendingConfirmation`, the client is on
 * site): its events are published, so the notification sync marks the
 * slot confirmed without a message, the « à confirmer » bell entry goes and
 * a portal booking is announced confirmed (AC 140).
 */
#[AsMessageHandler]
final readonly class CompleteAppointmentHandler
{
    public function __construct(
        private SilentPendingConfirmation $silentConfirmation,
        private AppointmentRepositoryInterface $appointmentRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CompleteAppointment $command): void
    {
        $appointment = $this->silentConfirmation->load(AppointmentId::fromString($command->appointmentId));
        if (null === $appointment) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId
            ));
        }

        $appointment->complete();
        $this->appointmentRepository->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
