<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query\GetAgendaForClinicDay;

use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDay\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDay\GetAgendaForClinicDay;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDay\GetAgendaForClinicDayHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetAgendaForClinicDayHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsAppointmentsForClinicAndDay(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'))
            ->create()
        ;
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 14:30:00'))
            ->create()
        ;
        // Different day — should not appear
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->startingAt(new \DateTimeImmutable('2026-04-11 09:00:00'))
            ->create()
        ;
        // Different clinic — should not appear
        AppointmentEntityFactory::new()
            ->withClinicId('99999999-9999-9999-9999-999999999999')
            ->startingAt(new \DateTimeImmutable('2026-04-10 10:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDayHandler::class);
        \assert($handler instanceof GetAgendaForClinicDayHandler);

        $items = $handler(new GetAgendaForClinicDay(
            clinicId: $clinicId,
            date: new \DateTimeImmutable('2026-04-10'),
        ));

        self::assertCount(2, $items);
        foreach ($items as $item) {
            self::assertInstanceOf(AppointmentItem::class, $item);
            self::assertSame($clinicId, $item->clinicId);
        }
    }

    public function testFiltersByPractitioner(): void
    {
        $clinicId           = '12345678-9abc-def0-1234-56789abcdef0';
        $practitionerUserId = '44444444-4444-4444-4444-444444444444';

        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId($practitionerUserId)
            ->startingAt(new \DateTimeImmutable('2026-04-10 09:00:00'))
            ->create()
        ;
        AppointmentEntityFactory::new()
            ->withClinicId($clinicId)
            ->withPractitionerUserId('55555555-5555-5555-5555-555555555555')
            ->startingAt(new \DateTimeImmutable('2026-04-10 10:00:00'))
            ->create()
        ;

        $handler = self::getContainer()->get(GetAgendaForClinicDayHandler::class);
        \assert($handler instanceof GetAgendaForClinicDayHandler);

        $items = $handler(new GetAgendaForClinicDay(
            clinicId: $clinicId,
            date: new \DateTimeImmutable('2026-04-10'),
            practitionerUserId: $practitionerUserId,
        ));

        self::assertCount(1, $items);
        self::assertSame($practitionerUserId, $items[0]->practitionerUserId);
    }

    public function testReturnsEmptyArrayWhenNoMatches(): void
    {
        $handler = self::getContainer()->get(GetAgendaForClinicDayHandler::class);
        \assert($handler instanceof GetAgendaForClinicDayHandler);

        $items = $handler(new GetAgendaForClinicDay(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            date: new \DateTimeImmutable('2026-04-10'),
        ));

        self::assertSame([], $items);
    }
}
