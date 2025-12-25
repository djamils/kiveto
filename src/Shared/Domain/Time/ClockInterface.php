<?php

declare(strict_types=1);

namespace App\Shared\Domain\Time;

/**
 * Time source abstraction.
 *
 * Use cases:
 * - SystemClock in production (real current time).
 * - FrozenClock in tests (deterministic "now" to avoid flaky tests).
 *
 * Do not call "new DateTimeImmutable()" directly in application services when "now" matters,
 * prefer injecting a ClockInterface.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
