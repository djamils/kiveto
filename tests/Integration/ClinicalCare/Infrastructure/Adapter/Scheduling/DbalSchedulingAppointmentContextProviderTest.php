<?php

declare(strict_types=1);

namespace App\Tests\Integration\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Fixtures\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\Scheduling\Factory\WaitingRoomEntryEntityFactory;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
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
            ->withOwnerId('33333333-3333-4333-8333-333333333333')
            ->withAnimalId('44444444-4444-4444-8444-444444444444')
            ->create()
        ;

        $provider = self::getContainer()->get(SchedulingAppointmentContextProviderInterface::class);
        \assert($provider instanceof SchedulingAppointmentContextProviderInterface);

        $context = $provider->getAppointmentContext(AppointmentId::fromString($appointmentId));

        self::assertSame($clinicId, $context->clinicId);
        self::assertSame('33333333-3333-4333-8333-333333333333', $context->ownerId);
        self::assertSame('44444444-4444-4444-8444-444444444444', $context->animalId);
        self::assertNull($context->linkedWaitingRoomEntryId);
        self::assertNull($context->arrivalMode);
        self::assertSame('PLANNED', $context->status);
    }

    public function testReturnsContextWithLinkedWaitingRoomEntry(): void
    {
        $appointmentId = '11111111-1111-4111-8111-111111111111';
        $clinicId      = '22222222-2222-4222-8222-222222222222';
        $entryId       = '55555555-5555-4555-8555-555555555555';

        AppointmentEntityFactory::new()
            ->withId($appointmentId)
            ->withClinicId($clinicId)
            ->create()
        ;

        WaitingRoomEntryEntityFactory::new()
            ->withId($entryId)
            ->withClinicId($clinicId)
            ->withLinkedAppointmentId($appointmentId)
            ->withArrivalMode(WaitingRoomArrivalMode::EMERGENCY)
            ->create()
        ;

        $provider = self::getContainer()->get(SchedulingAppointmentContextProviderInterface::class);
        \assert($provider instanceof SchedulingAppointmentContextProviderInterface);

        $context = $provider->getAppointmentContext(AppointmentId::fromString($appointmentId));

        self::assertSame($entryId, $context->linkedWaitingRoomEntryId);
        self::assertSame('EMERGENCY', $context->arrivalMode);
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
