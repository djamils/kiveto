<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PurgePractitionerDayLocks;

use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgePractitionerDayLocksHandler
{
    public const int KEPT_DAYS = 30;

    public function __construct(
        private PractitionerDayLockInterface $locks,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgePractitionerDayLocks $command): int
    {
        $cutoff = $this->clock->now()->modify(\sprintf('-%d days', self::KEPT_DAYS))->format('Y-m-d');

        return $this->locks->purgeBefore($cutoff);
    }
}
