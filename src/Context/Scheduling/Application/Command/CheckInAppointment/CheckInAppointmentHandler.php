<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CheckInAppointment;

use App\Context\Scheduling\Application\Service\SilentPendingConfirmation;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A pending request checked in here is confirmed silently first, under the
 * practitioner-day lock (`SilentPendingConfirmation`, the client is on
 * site): its events are published, so the notification sync marks the slot
 * confirmed without a message, the « à confirmer » bell entry goes and a
 * portal booking is announced confirmed (AC 140).
 */
#[AsMessageHandler]
final readonly class CheckInAppointmentHandler
{
    public function __construct(
        private SilentPendingConfirmation $silentConfirmation,
        private AppointmentRepositoryInterface $appointmentRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CheckInAppointment $command): void
    {
        $appointment = $this->silentConfirmation->load(
            AppointmentId::fromString($command->appointmentId),
            ClinicId::fromString($command->clinicId),
        );

        // Unknown, or of another clinic: nothing to record
        if (null === $appointment) {
            return;
        }

        $appointment->linkAdmission($command->admissionId);
        $appointment->complete();

        $this->appointmentRepository->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }
}
