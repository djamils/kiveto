<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\AnimalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwner\OwnerAppointmentItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The read models carry what a status means, computed in Scheduling, so
 * Presentation never compares status strings (S4a-F5, AC 89).
 */
final class AppointmentReadModelFlagsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{bool, bool, bool}}>
     */
    public static function provideEveryReadModelCarriesTheStatusFlagsCases(): iterable
    {
        // status → [occupiesSlot, isPending, isWithdrawn]
        yield 'planned' => ['PLANNED', [true, false, false]];
        yield 'pending' => ['PENDING_CLINIC_CONFIRMATION', [true, true, false]];
        yield 'cancelled' => ['CANCELLED', [false, false, true]];
        yield 'declined' => ['DECLINED', [false, false, true]];
        yield 'no-show' => ['NO_SHOW', [false, false, false]];
        yield 'completed' => ['COMPLETED', [false, false, false]];
    }

    /**
     * @param array{bool, bool, bool} $flags
     */
    #[DataProvider('provideEveryReadModelCarriesTheStatusFlagsCases')]
    public function testEveryReadModelCarriesTheStatusFlags(string $status, array $flags): void
    {
        $agenda  = new AppointmentItem('a', 'c', null, 'u', '2026-04-10 09:00:00', 30, $status, null, null, null);
        $animal  = new AnimalAppointmentItem('a', 'u', '2026-04-10 09:00:00', 30, $status, null);
        $owner   = new OwnerAppointmentItem('a', 'u', '2026-04-10 09:00:00', 30, $status, null, null, null);
        $details = new AppointmentDetails('a', 'c', null, 'u', '2026-04-10 09:00:00', 30, $status, null, null, null, '2026-04-01 09:00:00', '2026-04-01 09:00:00');

        foreach ([$agenda, $agenda->withIsOrphaned(true), $animal, $owner, $details] as $item) {
            self::assertSame($flags, [$item->occupiesSlot, $item->isPending, $item->isWithdrawn]);
        }
    }
}
