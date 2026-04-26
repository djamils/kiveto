<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\EventSubscriber;

use App\Context\Admission\Domain\Event\AdmissionOpenedWithUnidentifiedPatient;
use App\Context\Regulatory\Domain\MairieNotification;
use App\Context\Regulatory\Domain\Repository\MairieNotificationRepositoryInterface;
use App\Context\Regulatory\Domain\Repository\StrayCustodyRepositoryInterface;
use App\Context\Regulatory\Domain\Service\FrenchWorkingDayCalculator;
use App\Context\Regulatory\Domain\StrayCustody;
use App\Context\Regulatory\Domain\ValueObject\MairieNotificationId;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class AdmissionOpenedWithUnidentifiedPatientHandler
{
    public function __construct(
        private MairieNotificationRepositoryInterface $mairieRepo,
        private StrayCustodyRepositoryInterface $strayCustodyRepo,
        private FrenchWorkingDayCalculator $calculator,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdmissionOpenedWithUnidentifiedPatient $event): void
    {
        $now               = $this->clock->now();
        $admissionOpenedAt = new \DateTimeImmutable($event->openedAt);

        $notification = MairieNotification::schedule(
            id: MairieNotificationId::fromString($this->uuidGenerator->generate()),
            admissionId: $event->admissionId,
            patientId: $event->patientId,
            clinicId: $event->clinicId,
            admissionOpenedAt: $admissionOpenedAt,
            now: $now,
        );
        $this->mairieRepo->save($notification);
        $this->domainEventPublisher->publish($notification);

        $custody = StrayCustody::begin(
            id: StrayCustodyId::fromString($this->uuidGenerator->generate()),
            admissionId: $event->admissionId,
            patientId: $event->patientId,
            clinicId: $event->clinicId,
            admissionOpenedAt: $admissionOpenedAt,
            calculator: $this->calculator,
            now: $now,
        );
        $this->strayCustodyRepo->save($custody);
        $this->domainEventPublisher->publish($custody);
    }
}
