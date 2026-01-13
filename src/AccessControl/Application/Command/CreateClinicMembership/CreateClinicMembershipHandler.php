<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\CreateClinicMembership;

use App\AccessControl\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateClinicMembershipHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClinicRepositoryInterface $clinicRepository,
        private UserRepositoryInterface $userRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateClinicMembership $command): void
    {
        // Anti-corruption layer: convert from external BC IDs to local domain IDs
        $externalClinicId = \App\Clinic\Domain\ValueObject\ClinicId::fromString($command->clinicId);
        $externalUserId   = \App\IdentityAccess\Domain\ValueObject\UserId::fromString($command->userId);

        // Verify clinic exists (integration with Clinic BC)
        $clinic = $this->clinicRepository->findById($externalClinicId);
        if (null === $clinic) {
            throw new \InvalidArgumentException(\sprintf('Clinic with ID "%s" does not exist.', $command->clinicId));
        }

        // Verify user exists (integration with IdentityAccess BC)
        $user = $this->userRepository->findById($externalUserId);
        if (null === $user) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" does not exist.', $command->userId));
        }

        // Convert to local domain VOs
        $clinicId = ClinicId::fromString($command->clinicId);
        $userId   = UserId::fromString($command->userId);

        // Check for existing membership
        if ($this->membershipRepository->existsByClinicAndUser($clinicId, $userId)) {
            throw new ClinicMembershipAlreadyExistsException(
                \sprintf('User "%s" already has a membership in clinic "%s".', $command->userId, $command->clinicId)
            );
        }

        $membershipId = MembershipId::fromString($this->uuidGenerator->generate());
        $validFrom    = $command->validFrom ?? $this->clock->now();
        $createdAt    = $this->clock->now();

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: $clinicId,
            userId: $userId,
            role: $command->role,
            engagement: $command->engagement,
            validFrom: $validFrom,
            validUntil: $command->validUntil,
            createdAt: $createdAt,
        );

        $this->membershipRepository->save($membership);
    }
}
