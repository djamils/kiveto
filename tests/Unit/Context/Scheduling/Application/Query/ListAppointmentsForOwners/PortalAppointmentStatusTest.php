<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\PortalAppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PortalAppointmentStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{AppointmentStatus, ?CancellationActor, string}>
     */
    public static function provideEveryStatusHasItsPortalValueCases(): iterable
    {
        yield 'planned' => [AppointmentStatus::PLANNED, null, 'confirmed'];
        yield 'pending' => [AppointmentStatus::PENDING_CLINIC_CONFIRMATION, null, 'pending_clinic_confirmation'];
        yield 'cancelled by the client' => [AppointmentStatus::CANCELLED, CancellationActor::CLIENT, 'cancelled_by_client'];
        yield 'cancelled by the clinic' => [AppointmentStatus::CANCELLED, CancellationActor::CLINIC, 'cancelled_by_clinic'];
        yield 'cancelled before the Portal' => [AppointmentStatus::CANCELLED, null, 'cancelled_by_clinic'];
        yield 'declined' => [AppointmentStatus::DECLINED, null, 'declined'];
        yield 'completed' => [AppointmentStatus::COMPLETED, null, 'completed'];
        yield 'no show' => [AppointmentStatus::NO_SHOW, null, 'no_show'];
    }

    #[DataProvider('provideEveryStatusHasItsPortalValueCases')]
    public function testEveryStatusHasItsPortalValue(AppointmentStatus $status, ?CancellationActor $cancelledBy, string $expected): void
    {
        self::assertSame($expected, PortalAppointmentStatus::of($status, $cancelledBy)->value);
    }
}
