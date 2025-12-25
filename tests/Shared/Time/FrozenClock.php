<?php

declare(strict_types=1);

namespace App\Tests\Shared\Time;

use App\Shared\Domain\Time\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(private readonly \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
