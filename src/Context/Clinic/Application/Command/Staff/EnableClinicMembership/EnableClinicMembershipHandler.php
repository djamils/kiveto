<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\EnableClinicMembership;

use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableClinicMembershipHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(EnableClinicMembership $command): void
    {
        $membershipId = ClinicMembershipId::fromString($command->membershipId);

        $membership = $this->membershipRepository->findById($membershipId);
        if (null === $membership) {
            throw new \InvalidArgumentException(\sprintf('Membership "%s" not found.', $command->membershipId));
        }

        $membership->enable();

        $this->membershipRepository->save($membership);

        $this->domainEventPublisher->publish($membership);
    }
}
