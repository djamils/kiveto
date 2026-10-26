<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\ChangeReminderDelay;

use App\Context\Scheduling\Application\Command\ChangeReminderDelay\ChangeReminderDelay;
use App\Context\Scheduling\Application\Command\ChangeReminderDelay\ChangeReminderDelayHandler;
use App\Context\Scheduling\Application\Query\GetReminderDelay\GetReminderDelay;
use App\Context\Scheduling\Application\Query\GetReminderDelay\GetReminderDelayHandler;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryNotificationStates;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryReminderSettings;
use PHPUnit\Framework\TestCase;

final class ChangeReminderDelayHandlerTest extends TestCase
{
    private const string LATER     = '01960000-0000-7000-8000-0000000a0002';
    private const string REQUESTED = '01960000-0000-7000-8000-0000000a0003';
    private const string CANCELLED = '01960000-0000-7000-8000-0000000a0004';

    private InMemoryAppointments $appointments;
    private InMemoryNotificationStates $states;
    private InMemoryReminderSettings $settings;

    protected function setUp(): void
    {
        $this->appointments = new InMemoryAppointments();
        $this->states       = new InMemoryNotificationStates($this->appointments);
        $this->settings     = new InMemoryReminderSettings();
    }

    public function testFutureRemindersNotRequestedYetAreReplanned(): void
    {
        $now = Appointments::utc('2026-10-13 08:42');
        $this->plan(Appointments::ID, '2026-10-22 07:30', $now);
        $this->plan(self::LATER, '2026-10-23 12:00', $now);
        $this->plan(self::REQUESTED, '2026-10-24 07:30', $now, requestedAt: '2026-10-13 08:00');
        $this->plan(self::CANCELLED, '2026-10-25 07:30', $now, status: AppointmentStatus::CANCELLED);

        $this->handler($now)(new ChangeReminderDelay(Appointments::CLINIC, 'SAME_DAY_8'));

        // AC 22b: « Le matin même » now; the reminder already requested is unchanged.
        self::assertSame('2026-10-22 06:00', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame('2026-10-23 06:00', $this->states->of(self::LATER)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame('2026-10-23 07:30', $this->states->of(self::REQUESTED)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame('2026-10-24 06:30', $this->states->of(self::CANCELLED)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame('SAME_DAY_8', new GetReminderDelayHandler($this->settings)(new GetReminderDelay(Appointments::CLINIC)));
    }

    public function testTheSameDelayChangesNothing(): void
    {
        $now = Appointments::utc('2026-10-13 08:42');
        $this->plan(Appointments::ID, '2026-10-22 07:30', $now);
        // A reminder whose start moved without a sync yet: replanned only on a real change.
        $this->appointments->save(Appointments::planned('2026-10-29 07:30'));

        $this->handler($now)(new ChangeReminderDelay(Appointments::CLINIC, 'H24'));

        self::assertSame('2026-10-21 07:30', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame([], $this->settings->delays);
    }

    public function testAnUnknownDelayIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler(Appointments::utc('2026-10-13 08:42'))(new ChangeReminderDelay(Appointments::CLINIC, 'H72'));
    }

    public function testTheDefaultDelayIsReadForAClinicThatNeverChangedIt(): void
    {
        self::assertSame(ReminderDelay::H24->value, new GetReminderDelayHandler($this->settings)(new GetReminderDelay(Appointments::CLINIC)));
    }

    private function plan(string $id, string $startsAtUtc, \DateTimeImmutable $now, ?string $requestedAt = null, AppointmentStatus $status = AppointmentStatus::PLANNED): void
    {
        $this->appointments->save(Appointments::planned($startsAtUtc, id: $id, status: $status));

        $state = $this->states->lock(AppointmentId::fromString($id));
        \assert(null !== $state);
        $state->planReminder(ReminderDelay::H24, Appointments::utc($startsAtUtc), new \DateTimeZone('Europe/Paris'), $now);

        if (null !== $requestedAt) {
            $state->markReminderRequested(Appointments::utc($requestedAt));
        }

        $this->states->save($state);
    }

    private function handler(\DateTimeImmutable $now): ChangeReminderDelayHandler
    {
        return new ChangeReminderDelayHandler($this->settings, $this->states, new FakeClinicDirectory(), new FrozenClock($now));
    }
}
