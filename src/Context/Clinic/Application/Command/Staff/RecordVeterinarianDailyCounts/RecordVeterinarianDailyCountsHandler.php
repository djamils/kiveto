<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Context\Clinic\Application\Port\VeterinarianCountJobsInterface;
use App\Context\Clinic\Application\Port\VeterinarianDailyCountStoreInterface;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * D6: active veterinarians = VETERINARY memberships in force now whose user
 * account is ACTIVE. The day is the Europe/Paris day (the SMS quota month
 * is a Kiveto billing month, always in Paris time). Without a clinic id
 * (the nightly run), each clinic that is not CLOSED gets a job of its own.
 */
#[AsMessageHandler]
final readonly class RecordVeterinarianDailyCountsHandler
{
    private const string QUOTA_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        private ClinicReadRepositoryInterface $clinics,
        private ClinicMembershipRepositoryInterface $memberships,
        private UserAccountStatusInterface $accounts,
        private VeterinarianDailyCountStoreInterface $counts,
        private MessagingProfileSyncInterface $sync,
        private VeterinarianCountJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordVeterinarianDailyCounts $command): void
    {
        if (null === $command->clinicId) {
            foreach ($this->clinics->findAllFiltered()->clinics as $clinic) {
                if (ClinicStatus::CLOSED !== $clinic->status) {
                    $this->jobs->countLater($clinic->id);
                }
            }

            return;
        }

        $now           = $this->clock->now();
        $day           = $now->setTimezone(new \DateTimeZone(self::QUOTA_TIME_ZONE))->setTime(0, 0);
        $veterinarians = $this->memberships->findEffectiveUserIds(ClinicId::fromString($command->clinicId), $now, ClinicMemberRole::VETERINARY);
        $active        = [] === $veterinarians ? [] : $this->accounts->activeUserIds($veterinarians);
        $stored        = $this->counts->record($command->clinicId, $day, \count($active));

        $this->sync->pushVeterinarianCount($command->clinicId, $day->format('Y-m-d'), $stored);
    }
}
