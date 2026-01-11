<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\AddUserToClinic;

use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\ClinicAccess\Domain\ClinicMembership;
use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\ClinicAccess\Domain\ValueObject\MembershipId;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;

final readonly class AddUserToClinicHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClinicRepositoryInterface $clinicRepository,
        private UserRepositoryInterface $userRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddUserToClinic $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $userId   = UserId::fromString($command->userId);

        // Vérifier que la clinic existe
        $clinic = $this->clinicRepository->findById($clinicId);
        if (null === $clinic) {
            throw new \InvalidArgumentException(\sprintf('Clinic with ID "%s" does not exist.', $command->clinicId));
        }

        // Vérifier que le user existe
        $user = $this->userRepository->findById($userId);
        if (null === $user) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" does not exist.', $command->userId));
        }

        // Vérifier qu'il n'y a pas déjà une membership pour ce couple (clinic, user)
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
