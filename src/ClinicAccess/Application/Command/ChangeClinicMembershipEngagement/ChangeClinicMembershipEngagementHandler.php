<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\ChangeClinicMembershipEngagement;

use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\ClinicAccess\Domain\ValueObject\MembershipId;

final readonly class ChangeClinicMembershipEngagementHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(ChangeClinicMembershipEngagement $command): void
    {
        $membershipId = MembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership with ID "%s" does not exist.', $command->membershipId));
        }

        $membership->changeEngagement($command->engagement);

        $this->membershipRepository->save($membership);
    }
}
