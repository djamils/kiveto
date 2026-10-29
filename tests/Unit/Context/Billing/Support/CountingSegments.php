<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\SmsSegmentEstimatorInterface;

/** One segment per 40 characters of parameters, every estimate kept. */
final class CountingSegments implements SmsSegmentEstimatorInterface
{
    /** @var list<array<string, string>> */
    public array $asked = [];

    public function reminderSegments(string $clinicId, array $parameters): int
    {
        $this->asked[] = $parameters;

        return 1 + intdiv(mb_strlen(implode('', $parameters)), 40);
    }
}
