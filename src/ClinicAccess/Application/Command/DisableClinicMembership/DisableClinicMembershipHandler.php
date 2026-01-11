<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\DisableClinicMembership;

use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\ClinicAccess\Domain\ValueObject\MembershipId;

final readonly class DisableClinicMembershipHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(DisableClinicMembership $command): void
    {
        $membershipId = MembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        $membership->disable();

        $this->membershipRepository->save($membership);
    }
}
