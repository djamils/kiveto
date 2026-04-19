<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone;

use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateStaffProfilePhoneHandler
{
    public function __construct(
        private StaffProfileRepositoryInterface $profileRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateStaffProfilePhone $command): void
    {
        $profileId = StaffProfileId::fromString($command->profileId);

        $profile = $this->profileRepository->findById($profileId);
        if (null === $profile) {
            throw new \InvalidArgumentException(\sprintf('StaffProfile with ID "%s" not found.', $command->profileId));
        }

        $phoneNumber = null !== $command->phone ? PhoneNumber::fromString($command->phone) : null;

        $profile->changePhoneNumber($phoneNumber, $this->clock->now());

        $this->profileRepository->save($profile);
    }
}
