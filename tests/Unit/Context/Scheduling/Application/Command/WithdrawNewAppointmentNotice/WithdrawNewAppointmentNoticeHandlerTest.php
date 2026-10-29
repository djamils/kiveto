<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice;

use App\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice\WithdrawNewAppointmentNotice;
use App\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice\WithdrawNewAppointmentNoticeHandler;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingPractitionerBell;
use PHPUnit\Framework\TestCase;

final class WithdrawNewAppointmentNoticeHandlerTest extends TestCase
{
    public function testTheEntryOfTheAppointmentIsWithdrawn(): void
    {
        $bell = new CapturingPractitionerBell();

        new WithdrawNewAppointmentNoticeHandler($bell)(new WithdrawNewAppointmentNotice(Appointments::ID));

        self::assertSame(['new-appointment:' . Appointments::ID], $bell->withdrawn);
    }
}
