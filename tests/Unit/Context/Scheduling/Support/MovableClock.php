<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Shared\Domain\Time\ClockInterface;

final class MovableClock implements ClockInterface
{
    public function __construct(public \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function at(string $utc): void
    {
        $this->now = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    }
}
