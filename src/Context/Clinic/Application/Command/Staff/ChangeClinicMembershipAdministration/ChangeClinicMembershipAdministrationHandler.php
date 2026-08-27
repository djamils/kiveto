<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration;

use App\Context\Clinic\Application\Service\ClinicAdministrationInvariant;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClinicMembershipAdministrationHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClinicAdministrationInvariant $invariant,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeClinicMembershipAdministration $command): void
    {
        $membershipId = ClinicMembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        // The membership id comes off a URL. Refuse to touch one that belongs to
        // another clinic, whatever the caller's permissions say about their own.
        if (!$membership->clinicId()->equals(ClinicId::fromString($command->clinicId))) {
            throw new \InvalidArgumentException(\sprintf(
                'Membership "%s" does not belong to clinic "%s".',
                $command->membershipId,
                $command->clinicId,
            ));
        }

        // Nothing to do, and nothing to guard: the hat is already where the
        // caller wants it. Return before touching the repository.
        if ($membership->isClinicAdministrator() === $command->isClinicAdministrator) {
            return;
        }

        // Read the clock once: the pre-state and the post-state must be judged
        // at the same instant, or a window expiring between two reads makes us
        // refuse a write that did not cause anything.
        $now                       = $this->clock->now();
        $wasEffectiveAdministrator = $membership->isEffectiveAdministratorAt($now);

        if ($command->isClinicAdministrator) {
            $membership->grantAdministration();
        } else {
            $membership->revokeAdministration();
        }

        $this->invariant->assertNoKeyLost($membership, $wasEffectiveAdministrator, $now);

        $this->membershipRepository->save($membership);

        $this->domainEventPublisher->publish($membership);
    }
}
