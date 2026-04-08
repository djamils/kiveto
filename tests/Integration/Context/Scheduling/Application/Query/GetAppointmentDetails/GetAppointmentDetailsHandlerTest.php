<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetAppointmentDetails;

use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetailsHandler;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetAppointmentDetailsHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsAppointmentDetailsWhenFound(): void
    {
        $appointmentId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId      = '12345678-9abc-def0-1234-56789abcdef0';

        AppointmentEntityFactory::new()
            ->withId($appointmentId)
            ->withClinicId($clinicId)
            ->withOwnerId('22222222-2222-2222-2222-222222222222')
            ->withAnimalId('33333333-3333-3333-3333-333333333333')
            ->withPractitionerUserId('44444444-4444-4444-4444-444444444444')
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'), 45)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create([
                'reason' => 'Annual checkup',
                'notes'  => 'Anxious patient',
            ])
        ;

        $handler = self::getContainer()->get(GetAppointmentDetailsHandler::class);
        \assert($handler instanceof GetAppointmentDetailsHandler);

        $result = $handler(new GetAppointmentDetails($appointmentId));

        self::assertInstanceOf(AppointmentDetails::class, $result);
        self::assertSame($appointmentId, $result->id);
        self::assertSame($clinicId, $result->clinicId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $result->ownerId);
        self::assertSame('33333333-3333-3333-3333-333333333333', $result->animalId);
        self::assertSame('44444444-4444-4444-4444-444444444444', $result->practitionerUserId);
        self::assertSame(45, $result->durationMinutes);
        self::assertSame('PLANNED', $result->status);
        self::assertSame('Annual checkup', $result->reason);
        self::assertSame('Anxious patient', $result->notes);
    }

    public function testReturnsNullWhenAppointmentNotFound(): void
    {
        $handler = self::getContainer()->get(GetAppointmentDetailsHandler::class);
        \assert($handler instanceof GetAppointmentDetailsHandler);

        $result = $handler(new GetAppointmentDetails('00000000-0000-0000-0000-000000000000'));

        self::assertNull($result);
    }
}
