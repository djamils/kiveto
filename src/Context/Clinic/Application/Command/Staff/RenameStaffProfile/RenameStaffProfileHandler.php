<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RenameStaffProfile;

use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RenameStaffProfileHandler
{
    public function __construct(
        private StaffProfileRepositoryInterface $profileRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RenameStaffProfile $command): void
    {
        $profileId = StaffProfileId::fromString($command->profileId);

        $profile = $this->profileRepository->findById($profileId);
        if (null === $profile) {
            throw new \InvalidArgumentException(\sprintf('StaffProfile with ID "%s" not found.', $command->profileId));
        }

        $profile->rename(
            firstName: $command->firstName,
            lastName: $command->lastName,
            displayName: DisplayName::fromString($command->displayName),
            now: $this->clock->now(),
        );

        $this->profileRepository->save($profile);
    }
}
