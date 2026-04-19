<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences;

use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateStaffProfileAgendaPreferencesHandler
{
    public function __construct(
        private StaffProfileRepositoryInterface $profileRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateStaffProfileAgendaPreferences $command): void
    {
        $profileId = StaffProfileId::fromString($command->profileId);

        $profile = $this->profileRepository->findById($profileId);
        if (null === $profile) {
            throw new \InvalidArgumentException(\sprintf('StaffProfile with ID "%s" not found.', $command->profileId));
        }

        $profile->updateAgendaPreferences(
            agendaColor: HexColor::fromString($command->agendaColor),
            sortOrder: $command->sortOrder,
            isVisibleInAgenda: $command->isVisibleInAgenda,
            now: $this->clock->now(),
        );

        $this->profileRepository->save($profile);
    }
}
