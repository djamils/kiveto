<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Time;

use App\Shared\Domain\Time\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
