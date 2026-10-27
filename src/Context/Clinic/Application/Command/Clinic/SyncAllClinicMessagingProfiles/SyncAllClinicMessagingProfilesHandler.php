<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Service\MessagingProfilePublisher;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncAllClinicMessagingProfilesHandler
{
    public function __construct(
        private ClinicReadRepositoryInterface $clinics,
        private MessagingProfilePublisher $profiles,
        private CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @return int the number of clinics synchronised
     */
    public function __invoke(SyncAllClinicMessagingProfiles $command): int
    {
        $clinics = $this->clinics->findAllFiltered()->clinics;

        foreach ($clinics as $clinic) {
            $this->profiles->publish($clinic->id);
            // Counted right away: the fixtures and the console expect the quotas set on return.
            $this->commandBus->dispatch(new RecordVeterinarianDailyCounts($clinic->id));
        }

        return \count($clinics);
    }
}
