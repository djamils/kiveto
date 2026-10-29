<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Messaging\Consumer;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Domain\Event\UserActivatedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An invited veterinarian who activates their account becomes an active
 * veterinarian of every clinic they are a veterinarian of: those clinics
 * are counted again.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class UserActivatedConsumer
{
    public function __construct(
        private ClinicMembershipReadRepositoryInterface $memberships,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(UserActivatedIntegrationEvent $event): void
    {
        foreach ($this->memberships->findAccessibleClinicsForUser(UserId::fromString($event->userId)) as $clinic) {
            if (ClinicMemberRole::VETERINARY === $clinic->memberRole) {
                $this->commandBus->dispatch(new RecordVeterinarianDailyCounts($clinic->clinicId));
            }
        }
    }
}
