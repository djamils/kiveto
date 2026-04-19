<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole;

use App\Context\Clinic\Application\Exception\CannotChangeRoleWhileVeterinaryCredentialsExist;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClinicMembershipRoleHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private StaffProfileReadRepositoryInterface $profileReadRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeClinicMembershipRole $command): void
    {
        $membershipId = ClinicMembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        $hasCredentials = $this->profileReadRepository->hasVeterinaryCredentialsFor($membershipId);

        if ($hasCredentials && !$command->role->canHoldVeterinaryCredentials()) {
            throw new CannotChangeRoleWhileVeterinaryCredentialsExist(
                $command->membershipId,
                $command->role->value,
            );
        }

        $membership->changeRole($command->role);

        $this->membershipRepository->save($membership);

        $this->domainEventPublisher->publish($membership);
    }
}
