<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use PHPUnit\Framework\TestCase;

/**
 * AC 29: what each appointment status means, in one place.
 */
final class AppointmentStatusTest extends TestCase
{
    public function testOnlyPlannedAndPendingOccupyTheSlot(): void
    {
        $occupying = array_values(array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->occupiesSlot()));

        self::assertSame([AppointmentStatus::PLANNED, AppointmentStatus::PENDING_CLINIC_CONFIRMATION], $occupying);
    }

    public function testTheSqlListIsTheSameAsOccupiesSlot(): void
    {
        $occupying = array_map(
            static fn (AppointmentStatus $s): string => $s->value,
            array_values(array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->occupiesSlot())),
        );

        self::assertSame($occupying, AppointmentStatus::OCCUPYING_SQL_VALUES);
    }

    public function testTerminalStatuses(): void
    {
        $terminal = array_values(array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->isTerminal()));

        self::assertSame([AppointmentStatus::CANCELLED, AppointmentStatus::DECLINED, AppointmentStatus::NO_SHOW, AppointmentStatus::COMPLETED], $terminal);
    }

    public function testPendingAndWithdrawn(): void
    {
        $pending   = array_values(array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->isPending()));
        $withdrawn = array_values(array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $s): bool => $s->isWithdrawn()));

        self::assertSame([AppointmentStatus::PENDING_CLINIC_CONFIRMATION], $pending);
        self::assertSame([AppointmentStatus::CANCELLED, AppointmentStatus::DECLINED], $withdrawn);
    }

    public function testEveryStatusFitsTheColumn(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            self::assertLessThanOrEqual(32, \strlen($status->value));
        }
    }
}
