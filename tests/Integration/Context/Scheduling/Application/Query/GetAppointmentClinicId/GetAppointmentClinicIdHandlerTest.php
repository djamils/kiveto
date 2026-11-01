<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetAppointmentClinicId;

use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicId;
use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicIdHandler;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetAppointmentClinicIdHandlerTest extends KernelTestCase
{
    use Factories;

    private const string APPOINTMENT_ID = '01960000-0000-7000-8000-0000000a0c01';
    private const string CLINIC_ID      = '01960000-0000-7000-8000-0000000c0c01';

    public function testReturnsTheClinicOfTheAppointment(): void
    {
        AppointmentEntityFactory::new()->withId(self::APPOINTMENT_ID)->withClinicId(self::CLINIC_ID)->create();

        self::assertSame(self::CLINIC_ID, $this->handler()(new GetAppointmentClinicId(self::APPOINTMENT_ID)));
    }

    public function testReturnsNullForAnUnknownAppointment(): void
    {
        self::assertNull($this->handler()(new GetAppointmentClinicId(self::APPOINTMENT_ID)));
    }

    private function handler(): GetAppointmentClinicIdHandler
    {
        $handler = self::getContainer()->get(GetAppointmentClinicIdHandler::class);
        \assert($handler instanceof GetAppointmentClinicIdHandler);

        return $handler;
    }
}
