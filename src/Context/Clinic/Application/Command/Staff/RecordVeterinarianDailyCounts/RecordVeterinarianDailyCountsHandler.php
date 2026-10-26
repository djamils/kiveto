<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Application\Port\MessagingProfileSyncInterface;
use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Context\Clinic\Application\Port\VeterinarianDailyCountStoreInterface;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * D6: active veterinarians = VETERINARY memberships in force now whose user
 * account is ACTIVE. The day is the Europe/Paris day (the SMS quota month
 * is a Kiveto billing month, always in Paris time).
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
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordVeterinarianDailyCounts $command): void
    {
        $now = $this->clock->now();
        $day = $now->setTimezone(new \DateTimeZone(self::QUOTA_TIME_ZONE))->setTime(0, 0);

        $clinicIds = null !== $command->clinicId
            ? [$command->clinicId]
            : array_map(static fn (ClinicDto $clinic): string => $clinic->id, $this->clinics->findAllFiltered()->clinics);

        foreach ($clinicIds as $clinicId) {
            $veterinarians = $this->memberships->findEffectiveUserIds(ClinicId::fromString($clinicId), $now, ClinicMemberRole::VETERINARY);
            $active        = [] === $veterinarians ? [] : $this->accounts->activeUserIds($veterinarians);
            $stored        = $this->counts->record($clinicId, $day, \count($active));

            $this->sync->pushVeterinarianCount($clinicId, $day->format('Y-m-d'), $stored);
        }
    }
}
