<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\EnableClinicMembership;

use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\ClinicAccess\Domain\ValueObject\MembershipId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableClinicMembershipHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(EnableClinicMembership $command): void
    {
        $membershipId = MembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        $membership->enable();

        $this->membershipRepository->save($membership);
    }
}
