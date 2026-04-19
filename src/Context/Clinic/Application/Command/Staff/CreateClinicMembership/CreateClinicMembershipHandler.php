<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\CreateClinicMembership;

use App\Context\Clinic\Application\Exception\CannotCreatePractitionerMembershipWithoutProfile;
use App\Context\Clinic\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateClinicMembershipHandler
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $membershipRepository,
        private ClinicRepositoryInterface $clinicRepository,
        private UserExistenceCheckerInterface $userExistenceChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CreateClinicMembership $command): void
    {
        if (\in_array($command->role, [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT], true)) {
            throw new CannotCreatePractitionerMembershipWithoutProfile($command->role->value);
        }

        $clinicId = ClinicId::fromString($command->clinicId);

        // Verify clinic exists (local — same BC)
        $clinic = $this->clinicRepository->findById($clinicId);
        if (null === $clinic) {
            throw new \InvalidArgumentException(\sprintf('Clinic with ID "%s" does not exist.', $command->clinicId));
        }

        // Verify user exists (cross-BC via port)
        if (!$this->userExistenceChecker->exists($command->userId)) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" does not exist.', $command->userId));
        }

        $userId = UserId::fromString($command->userId);

        // Check for existing membership
        if ($this->membershipRepository->existsByClinicAndUser($clinicId, $userId)) {
            throw new ClinicMembershipAlreadyExistsException(
                \sprintf('User "%s" already has a membership in clinic "%s".', $command->userId, $command->clinicId)
            );
        }

        $membershipId = ClinicMembershipId::fromString($this->uuidGenerator->generate());
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

        $this->domainEventPublisher->publish($membership);
    }
}
