<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CheckInAppointment;

use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointmentHandler;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use PHPUnit\Framework\TestCase;

/**
 * AC 140: a pending request checked in at the desk is confirmed silently,
 * and its events are published (no confirmation message, the integration
 * bridge and the bell see it).
 */
final class CheckInAppointmentHandlerTest extends TestCase
{
    private const string ADMISSION = '01960000-0000-7000-8000-0000000b0001';

    private InMemoryAppointments $appointments;
    private CapturingEventBus $events;

    protected function setUp(): void
    {
        $this->appointments = new InMemoryAppointments();
        $this->events       = new CapturingEventBus();
    }

    public function testAPendingRequestCheckedInIsConfirmedSilentlyThenCompleted(): void
    {
        $this->appointments->save(Appointments::pending());

        $this->checkIn(Appointments::CLINIC);

        $appointment = $this->appointments->findById(AppointmentId::fromString(Appointments::ID));
        self::assertSame([AppointmentStatus::COMPLETED, true, self::ADMISSION], [$appointment?->status(), $appointment?->confirmedSilently(), $appointment?->linkedAdmissionId()]);
        self::assertSame([AppointmentConfirmed::class, AppointmentCompleted::class], array_map(static fn (object $e): string => $e::class, $this->events->events));
        $confirmed = $this->events->events[0];
        self::assertInstanceOf(AppointmentConfirmed::class, $confirmed);
        self::assertSame([true, 'portal'], [$confirmed->silent(), $confirmed->source()]);
    }

    public function testAnUnknownAppointmentOrOneOfAnotherClinicIsIgnored(): void
    {
        $this->checkIn(Appointments::CLINIC);

        $this->appointments->save(Appointments::planned());
        $this->checkIn('01960000-0000-7000-8000-0000000c0009');

        self::assertSame(AppointmentStatus::PLANNED, $this->appointments->findById(AppointmentId::fromString(Appointments::ID))?->status());
        self::assertSame([], $this->events->events);
    }

    private function checkIn(string $clinicId): void
    {
        new CheckInAppointmentHandler($this->appointments, new DomainEventPublisher($this->events))(
            new CheckInAppointment($clinicId, Appointments::ID, self::ADMISSION),
        );
    }
}
