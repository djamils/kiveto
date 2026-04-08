<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\System\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\MembershipId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClinicMembershipRoleHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
    ) {
    }

    public function __invoke(ChangeClinicMembershipRole $command): void
    {
        $membershipId = MembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        $membership->changeRole($command->role);

        $this->membershipRepository->save($membership);
    }
}
