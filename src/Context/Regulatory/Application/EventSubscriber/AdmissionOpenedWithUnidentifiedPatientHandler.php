<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\EventSubscriber;

use App\Context\Admission\Domain\Event\AdmissionOpenedWithUnidentifiedPatient;
use App\Context\Regulatory\Domain\AuthorityNotification;
use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\Repository\AuthorityNotificationRepositoryInterface;
use App\Context\Regulatory\Domain\Repository\StrayCustodyRepositoryInterface;
use App\Context\Regulatory\Domain\StrayCustody;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationId;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class AdmissionOpenedWithUnidentifiedPatientHandler
{
    public function __construct(
        private AuthorityNotificationRepositoryInterface $authorityNotificationRepo,
        private StrayCustodyRepositoryInterface $strayCustodyRepo,
        private RegulatoryPolicyInterface $policy,
        private DomainEventPublisher $domainEventPublisher,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdmissionOpenedWithUnidentifiedPatient $event): void
    {
        $now               = $this->clock->now();
        $admissionOpenedAt = new \DateTimeImmutable($event->openedAt);

        $notification = AuthorityNotification::schedule(
            id: AuthorityNotificationId::fromString($this->uuidGenerator->generate()),
            admissionId: $event->admissionId,
            patientId: $event->patientId,
            clinicId: $event->clinicId,
            admissionOpenedAt: $admissionOpenedAt,
            policy: $this->policy,
            now: $now,
        );
        $this->authorityNotificationRepo->save($notification);
        $this->domainEventPublisher->publish($notification);

        $custody = StrayCustody::begin(
            id: StrayCustodyId::fromString($this->uuidGenerator->generate()),
            admissionId: $event->admissionId,
            patientId: $event->patientId,
            clinicId: $event->clinicId,
            admissionOpenedAt: $admissionOpenedAt,
            policy: $this->policy,
            now: $now,
        );
        $this->strayCustodyRepo->save($custody);
        $this->domainEventPublisher->publish($custody);
    }
}
