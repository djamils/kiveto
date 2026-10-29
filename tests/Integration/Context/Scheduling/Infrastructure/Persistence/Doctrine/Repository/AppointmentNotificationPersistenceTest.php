<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Notification\AppointmentMessageKind;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentNotificationEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\ClinicSchedulingSettingsEntity;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class AppointmentNotificationPersistenceTest extends KernelTestCase
{
    private const string OTHER = '01960000-0000-7000-8000-0000000a0099';

    public function testTheStateOfAnAppointmentIsCreatedOnFirstLockThenKept(): void
    {
        $this->appointments()->save(Appointments::planned());
        $states = $this->states();

        $state = $states->lock(AppointmentId::fromString(Appointments::ID));
        self::assertNotNull($state);
        self::assertSame([Appointments::CLINIC, 0, false, null], [$state->clinicId()->toString(), $state->notificationCounter(), $state->isSilenced(), $state->notified()]);

        $state->recordNotified(AppointmentMessageKind::CONFIRMATION, Appointments::slot('2026-10-22 07:30'), Appointments::ROUSSEAU, Appointments::utc('2026-10-13 08:44'), Appointments::OWNER);
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-22 07:30'), new \DateTimeZone('Europe/Paris'), Appointments::utc('2026-10-13 08:44'));
        $states->save($state);

        $again = $states->lock(AppointmentId::fromString(Appointments::ID));
        self::assertNotNull($again);
        self::assertSame(1, $again->notificationCounter());
        self::assertSame(Appointments::OWNER, $again->notifiedOwnerId());
        self::assertSame('2026-10-13 08:44', $again->confirmedAt()?->format('Y-m-d H:i'));
        $notified = $again->notified();
        self::assertNotNull($notified);
        self::assertSame(['2026-10-22 07:30', 30, Appointments::ROUSSEAU], [$notified->startsAtUtc->format('Y-m-d H:i'), $notified->durationMinutes, $notified->practitionerUserId]);
        self::assertSame('2026-10-21 07:30', $again->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertNull($again->reminderRequestedAt());
        self::assertNull($again->cancellationNotifiedAt());
    }

    public function testAnUnknownAppointmentHasNoState(): void
    {
        self::assertNull($this->states()->lock(AppointmentId::fromString(self::OTHER)));
        self::assertNull($this->states()->findForUpdate(AppointmentId::fromString(self::OTHER)));
    }

    public function testACancellationIsSilencedEvenBeforeAnySync(): void
    {
        $states = $this->states();
        $states->silence(AppointmentId::fromString(self::OTHER), ClinicId::fromString(Appointments::CLINIC));
        $states->silence(AppointmentId::fromString(self::OTHER), ClinicId::fromString(Appointments::CLINIC));

        self::assertTrue($states->findForUpdate(AppointmentId::fromString(self::OTHER))?->isSilenced());
    }

    public function testDueRemindersAreListedUntilRequested(): void
    {
        $this->appointments()->save(Appointments::planned());
        $states = $this->states();
        $state  = $states->lock(AppointmentId::fromString(Appointments::ID));
        \assert(null !== $state);
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-22 07:30'), new \DateTimeZone('Europe/Paris'), Appointments::utc('2026-10-13 08:44'));
        $states->save($state);

        self::assertSame([], $states->dueReminders(Appointments::utc('2026-10-21 07:29')));
        self::assertSame([Appointments::ID], $states->dueReminders(Appointments::utc('2026-10-21 07:30')));

        $state->markReminderRequested(Appointments::utc('2026-10-21 07:31'));
        $states->save($state);
        self::assertSame([], $states->dueReminders(Appointments::utc('2026-10-21 08:00')));
        self::assertSame('2026-10-21 07:31', $states->findForUpdate(AppointmentId::fromString(Appointments::ID))?->reminderRequestedAt()?->format('Y-m-d H:i'));
    }

    public function testPendingRemindersAreThoseOfPlannedAppointmentsToCome(): void
    {
        $this->appointments()->save(Appointments::planned());
        $this->appointments()->save(Appointments::planned('2026-10-23 07:30', id: self::OTHER, status: AppointmentStatus::CANCELLED));
        $states = $this->states();
        $states->lock(AppointmentId::fromString(Appointments::ID));
        $states->lock(AppointmentId::fromString(self::OTHER));

        $pending = $states->lockPendingReminders(ClinicId::fromString(Appointments::CLINIC), Appointments::utc('2026-10-13 08:44'));

        self::assertCount(1, $pending);
        self::assertSame(Appointments::ID, $pending[0]->state->appointmentId()->toString());
        self::assertSame('2026-10-22 07:30', $pending[0]->startsAtUtc->format('Y-m-d H:i'));
        self::assertSame([], $states->lockPendingReminders(ClinicId::fromString(Appointments::CLINIC), Appointments::utc('2026-10-22 08:00')));
    }

    public function testTheReminderDelayDefaultsTo24HoursUntilChanged(): void
    {
        $settings = self::getContainer()->get(ReminderSettingsRepositoryInterface::class);
        \assert($settings instanceof ReminderSettingsRepositoryInterface);
        $clinic = ClinicId::fromString(Appointments::CLINIC);

        self::assertSame(ReminderDelay::H24, $settings->reminderDelay($clinic));
        $settings->saveReminderDelay($clinic, ReminderDelay::H48);
        $settings->saveReminderDelay($clinic, ReminderDelay::SAME_DAY_8);
        self::assertSame(ReminderDelay::SAME_DAY_8, $settings->reminderDelay($clinic));
    }

    public function testTheMappingEntitiesHoldTheirColumns(): void
    {
        $id = Uuid::fromString(Appointments::ID);

        self::assertSame($id, new AppointmentNotificationEntity($id, $id)->columns()['appointment_id']);
        self::assertNull(new AppointmentNotificationEntity($id, $id)->columns()['notified_owner_id']);
        self::assertSame(['clinic_id' => $id, 'reminder_delay' => 'H24'], new ClinicSchedulingSettingsEntity($id)->columns());
    }

    private function appointments(): AppointmentRepositoryInterface
    {
        $repository = self::getContainer()->get(AppointmentRepositoryInterface::class);
        \assert($repository instanceof AppointmentRepositoryInterface);

        return $repository;
    }

    private function states(): AppointmentNotificationStateRepositoryInterface
    {
        $repository = self::getContainer()->get(AppointmentNotificationStateRepositoryInterface::class);
        \assert($repository instanceof AppointmentNotificationStateRepositoryInterface);

        return $repository;
    }
}
