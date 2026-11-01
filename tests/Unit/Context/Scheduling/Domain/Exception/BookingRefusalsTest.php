<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Exception;

use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\BlockRefusesAppointmentsException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Context\Scheduling\Domain\Exception\PractitionerNotBookableException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use PHPUnit\Framework\TestCase;

/**
 * The agenda turns these English messages into its French toasts by keyword
 * (agenda.js `message()`): « capacity », « block », « eligible »; an overlap
 * or a contended lock falls back on APPOINTMENT_CONFLICT.
 */
final class BookingRefusalsTest extends TestCase
{
    public function testMessagesKeepTheKeywordsOfTheAgendaToasts(): void
    {
        $overlap = SlotTakenException::overlap('u-1', new \DateTimeImmutable('2026-03-11 09:00'));
        self::assertSame('Practitioner "u-1" has an overlapping appointment at 2026-03-11 09:00.', $overlap->getMessage());
        self::assertFalse($overlap->isCapacity());

        $full = SlotTakenException::capacityReached();
        self::assertStringContainsString('capacity', $full->getMessage());
        self::assertTrue($full->isCapacity());

        $deadlock  = new \RuntimeException('deadlock');
        $contended = SlotTakenException::contended($deadlock);
        self::assertSame(SlotTakenException::CONTENDED, $contended->reason);
        self::assertSame($deadlock, $contended->getPrevious());
        self::assertDoesNotMatchRegularExpression('/capacity|block|eligible/i', $contended->getMessage());

        self::assertSame('Cannot schedule appointment in a leave block.', BlockRefusesAppointmentsException::ofType(PlanningBlockType::LEAVE)->getMessage());
        self::assertSame('User "u-1" is not eligible as practitioner for clinic "c-1".', PractitionerNotBookableException::at('u-1', 'c-1')->getMessage());
    }

    public function testConfirmAndDeclineRefusalsAreShownAsIs(): void
    {
        self::assertSame('Demande expirée.', new PendingAppointmentExpiredException()->getMessage());
        self::assertSame('Ce RDV a déjà été traité.', new AppointmentAlreadyHandledException()->getMessage());
        self::assertInstanceOf(\DomainException::class, new AppointmentAlreadyHandledException());
    }
}
