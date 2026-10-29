<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Domain\Quota\SmsThreshold;
use App\System\Notification\Domain\Quota\SmsThresholdRepositoryInterface;

final class InMemorySmsThresholds implements SmsThresholdRepositoryInterface
{
    /** @var array<string, \DateTimeImmutable> */
    public array $crossed = [];

    public function markCrossed(string $clinicId, string $month, SmsThreshold $threshold, \DateTimeImmutable $at): bool
    {
        $key = $clinicId . '|' . $month . '|' . $threshold->value;

        if (isset($this->crossed[$key])) {
            return false;
        }

        $this->crossed[$key] = $at;

        return true;
    }
}
