<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Adapter\Scheduling;

use App\Context\Consultation\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalSchedulingAppointmentContextProviderTest extends KernelTestCase
{
    use Factories;

    public function testReturnsContextWhenAppointmentExists(): void
    {
        $appointmentId = '11111111-1111-4111-8111-111111111111';
        $clinicId      = '22222222-2222-4222-8222-222222222222';

        AppointmentEntityFactory::new()
            ->withId($appointmentId)
            ->withClinicId($clinicId)
            ->create()
        ;

        $provider = self::getContainer()->get(SchedulingAppointmentContextProviderInterface::class);
        \assert($provider instanceof SchedulingAppointmentContextProviderInterface);

        $context = $provider->getAppointmentContext(AppointmentId::fromString($appointmentId));

        self::assertSame($clinicId, $context->clinicId);
        self::assertNull($context->admissionId);
        self::assertSame('PLANNED', $context->status);
    }

    public function testThrowsWhenAppointmentNotFound(): void
    {
        $provider = self::getContainer()->get(SchedulingAppointmentContextProviderInterface::class);
        \assert($provider instanceof SchedulingAppointmentContextProviderInterface);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Appointment not found');

        $provider->getAppointmentContext(
            AppointmentId::fromString('00000000-0000-4000-8000-000000000000'),
        );
    }
}
