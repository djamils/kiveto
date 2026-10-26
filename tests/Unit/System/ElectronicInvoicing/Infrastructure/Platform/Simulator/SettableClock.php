<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Domain\Time\ClockInterface;

final class SettableClock implements ClockInterface
{
    public \DateTimeImmutable $now;

    public function __construct(string $now = '2026-10-21T10:00:00+00:00')
    {
        $this->now = new \DateTimeImmutable($now);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
