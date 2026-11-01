<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * Records the practitioner-day keys a handler locks, as "<practitioner>|<date>".
 */
final class RecordingDayLock implements PractitionerDayLockInterface
{
    /** @var list<string> */
    public array $locked = [];

    public ?string $clinicId = null;

    /** @var list<string> */
    public array $purgedBefore = [];

    public function lock(ClinicId $clinicId, array $keys): void
    {
        $this->clinicId = $clinicId->toString();
        foreach (LockKeys::merge($keys) as $key) {
            $this->locked[] = $key->key();
        }
    }

    public function purgeBefore(string $localDate): int
    {
        $this->purgedBefore[] = $localDate;

        return 3;
    }
}
