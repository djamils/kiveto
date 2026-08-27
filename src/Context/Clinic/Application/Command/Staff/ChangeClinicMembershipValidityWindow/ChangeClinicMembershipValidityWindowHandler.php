<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow;

use App\Context\Clinic\Application\Service\ClinicAdministrationInvariant;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClinicMembershipValidityWindowHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClinicAdministrationInvariant $invariant,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeClinicMembershipValidityWindow $command): void
    {
        $membershipId = ClinicMembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        // Two ways to make a membership ineffective without touching its status:
        // validUntil moved into the past, or validFrom moved into the future.
        $now                       = $this->clock->now();
        $wasEffectiveAdministrator = $membership->isEffectiveAdministratorAt($now);

        $membership->changeValidityWindow($command->validFrom, $command->validUntil);

        $this->invariant->assertNoKeyLost($membership, $wasEffectiveAdministrator, $now);

        $this->membershipRepository->save($membership);

        $this->domainEventPublisher->publish($membership);
    }
}
