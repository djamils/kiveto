<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\RequestAppointmentReminder;

use App\Context\Scheduling\Application\Command\RequestAppointmentReminder\RequestAppointmentReminder;
use App\Context\Scheduling\Application\Command\RequestAppointmentReminder\RequestAppointmentReminderHandler;
use App\Context\Scheduling\Application\Command\RequestDueReminders\RequestDueReminders;
use App\Context\Scheduling\Application\Command\RequestDueReminders\RequestDueRemindersHandler;
use App\Context\Scheduling\Application\Notification\AppointmentMessageComposer;
use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Notification\AppointmentMessageKind;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Infrastructure\Calendar\IcsWriter;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingJobs;
use App\Tests\Unit\Context\Scheduling\Support\CapturingNotifications;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAttachments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryNotificationStates;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryReminderSettings;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 15-minute scan and the reminder of each due appointment, re-checked
 * at send time (AC 19, 21, 36).
 */
final class RequestAppointmentReminderHandlerTest extends TestCase
{
    private InMemoryAppointments $appointments;
    private InMemoryNotificationStates $states;
    private InMemoryReminderSettings $settings;
    private FakeClientContacts $contacts;
    private CapturingNotifications $notifications;
    private MovableClock $clock;

    protected function setUp(): void
    {
        $this->appointments  = new InMemoryAppointments();
        $this->states        = new InMemoryNotificationStates($this->appointments);
        $this->settings      = new InMemoryReminderSettings();
        $this->contacts      = new FakeClientContacts();
        $this->notifications = new CapturingNotifications();
        $this->clock         = new MovableClock(Appointments::utc('2026-10-13 08:44'));

        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', reminderChannels: [ClientContact::SMS]);
    }

    /**
     * Thursday 22/10 09:30 Paris; the scan runs at the due time.
     *
     * @return iterable<string, array{ReminderDelay, string, string}>
     */
    public static function provideTheScanRemindsEachDueAppointmentOnceCases(): iterable
    {
        yield '24 h avant' => [ReminderDelay::H24, '2026-10-21 07:30', 'demain à 9h30'];
        yield '48 h avant' => [ReminderDelay::H48, '2026-10-20 07:30', 'jeudi 22/10 à 9h30'];
        yield 'le matin même' => [ReminderDelay::SAME_DAY_8, '2026-10-22 06:00', "aujourd'hui à 9h30"];
    }

    #[DataProvider('provideTheScanRemindsEachDueAppointmentOnceCases')]
    public function testTheScanRemindsEachDueAppointmentOnce(ReminderDelay $delay, string $scanAt, string $when): void
    {
        $this->plan(Appointments::planned(), $delay);

        $this->clock->at($scanAt);
        $jobs = new CapturingJobs();
        self::assertSame(1, new RequestDueRemindersHandler($this->states, $jobs, $this->clock)(new RequestDueReminders()));
        self::assertSame([Appointments::ID], $jobs->reminders);

        $this->remind();
        $this->remind();

        // AC 19: one SMS « …rappel du RDV de Milo demain à 9h30… », one per appointment ever.
        self::assertCount(1, $this->notifications->messages);
        $reminder = $this->notifications->messages[0];
        self::assertSame([AppointmentMessage::REMINDER, ClientContact::SMS, '+33612345678', 'appointment:' . Appointments::ID . ':reminder'], [$reminder->purpose, $reminder->channel, $reminder->recipientAddress, $reminder->idempotencyKey]);
        self::assertSame(['animalName' => 'Milo', 'whenLabel' => $when, 'practitionerName' => 'Dr Rousseau'], $reminder->parameters);
        self::assertNull($reminder->fallbackAddress);
        self::assertSame($scanAt, $this->states->of(Appointments::ID)->reminderRequestedAt()?->format('Y-m-d H:i'));
        self::assertSame(0, new RequestDueRemindersHandler($this->states, new CapturingJobs(), $this->clock)(new RequestDueReminders()));
    }

    public function testTheEmailFallbackIsOfferedOnlyWhenTheConsentAllowsEmail(): void
    {
        // AC 36: the cap may hold the SMS back; the e-mail goes instead only if the reminder consent allows it.
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', reminderChannels: [ClientContact::SMS, ClientContact::EMAIL]);
        $this->plan(Appointments::planned());

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        $reminder = $this->notifications->messages[0];
        self::assertSame('s.dubois@email.fr', $reminder->fallbackAddress);
        self::assertSame(['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau'], $reminder->fallbackParameters);
    }

    public function testNoReminderWithoutTheSmsConsentOrAMobile(): void
    {
        // AC 21: the client switched the SMS reminders off.
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', reminderChannels: [ClientContact::EMAIL]);
        $this->plan(Appointments::planned());

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        self::assertSame([], $this->notifications->messages);
        self::assertNull($this->states->of(Appointments::ID)->reminderDueAt());
        self::assertSame([], $this->states->dueReminders($this->clock->now()));
    }

    public function testNoReminderForACancelledPastOrOwnerlessAppointment(): void
    {
        $this->clock->at('2026-10-21 07:30');

        $variants = [
            '01960000-0000-7000-8000-0000000a0011' => Appointments::planned(id: '01960000-0000-7000-8000-0000000a0011', status: AppointmentStatus::CANCELLED),
            '01960000-0000-7000-8000-0000000a0012' => Appointments::planned('2026-10-21 07:00', id: '01960000-0000-7000-8000-0000000a0012'),
            '01960000-0000-7000-8000-0000000a0013' => Appointments::planned(id: '01960000-0000-7000-8000-0000000a0013', ownerId: null),
            '01960000-0000-7000-8000-0000000a0014' => Appointments::planned(id: '01960000-0000-7000-8000-0000000a0014', ownerId: '01960000-0000-7000-8000-0000000e0099'),
        ];

        foreach ($variants as $id => $appointment) {
            $this->plan(Appointments::planned(id: $id), now: '2026-10-13 08:44');
            $this->appointments->save($appointment);
            $this->remind($id);
        }

        self::assertSame([], $this->notifications->messages);
    }

    public function testAReminderNotDueYetOrWithoutStateWaits(): void
    {
        $this->plan(Appointments::planned());

        $this->clock->at('2026-10-21 07:29');
        $this->remind();
        $this->remind('01960000-0000-7000-8000-0000000a0099');

        self::assertSame([], $this->notifications->messages);
        self::assertSame('2026-10-21 07:30', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
    }

    public function testAnArchivedOwnerIsNotReminded(): void
    {
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', reminderChannels: [ClientContact::SMS], archived: true);
        $this->plan(Appointments::planned());

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        self::assertSame([], $this->notifications->messages);
    }

    public function testAMoveNotSyncedYetPushesTheReminderBack(): void
    {
        // Thursday 22/10 09:30 Paris, reminded 24 h before; at 09:29 on Wednesday the appointment
        // moves to Friday 23/10 09:30 — its sync runs two minutes later, after the 09:30 scan.
        $this->plan(Appointments::planned());
        $this->appointments->save(Appointments::planned('2026-10-23 07:30'));

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        // Nothing goes now: the reminder follows the current start.
        self::assertSame([], $this->notifications->messages);
        self::assertSame('2026-10-22 07:30', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertNull($this->states->of(Appointments::ID)->reminderRequestedAt());

        // It goes at the new due time, quoting the new slot.
        $this->clock->at('2026-10-22 07:30');
        $this->remind();
        self::assertCount(1, $this->notifications->messages);
        self::assertSame('demain à 9h30', $this->notifications->messages[0]->parameters['whenLabel']);
    }

    public function testAReminderAlreadyDueStaysDueWhenTheAppointmentMovedEarlier(): void
    {
        // Moved from Thursday 09:30 to Thursday 08:00 Paris: the 24 h mark is past, the client still gets it now.
        $this->plan(Appointments::planned());
        $this->appointments->save(Appointments::planned('2026-10-22 06:00'));

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        self::assertCount(1, $this->notifications->messages);
        self::assertSame('demain à 8h00', $this->notifications->messages[0]->parameters['whenLabel']);
    }

    public function testNoReminderForAnAppointmentAboutToStart(): void
    {
        // The scan ran late and the appointment was moved to start in 20 minutes: no reminder any more.
        $this->plan(Appointments::planned());
        $this->appointments->save(Appointments::planned('2026-10-21 07:50'));

        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        self::assertSame([], $this->notifications->messages);
        self::assertNull($this->states->of(Appointments::ID)->reminderDueAt());
    }

    public function testANewOwnerIsRemindedOnlyOnceTheSyncConfirmedTheAppointmentToThem(): void
    {
        $this->contacts->contacts[Appointments::LEFEVRE] = new ClientContact(Appointments::LEFEVRE, 'Marc Lefèvre', 'Monsieur Lefèvre', null, '+33698765432', ClientContact::SMS, [ClientContact::SMS], false);
        $this->plan(Appointments::planned());
        $state = $this->states->of(Appointments::ID);
        $state->recordNotified(AppointmentMessageKind::CONFIRMATION, Appointments::slot('2026-10-22 07:30'), Appointments::ROUSSEAU, Appointments::utc('2026-10-13 08:44'), Appointments::OWNER);
        $this->states->save($state);

        // Given to Marc a minute before the scan: his confirmation is still in the debounce window.
        $this->appointments->save(Appointments::planned(ownerId: Appointments::LEFEVRE));
        $this->clock->at('2026-10-21 07:30');
        $this->remind();

        self::assertSame([], $this->notifications->messages);
        self::assertSame([Appointments::ID], $this->states->dueReminders($this->clock->now()));

        // Once confirmed to him, the next scan reminds him.
        $state = $this->states->of(Appointments::ID);
        $state->releaseOwner();
        $state->recordNotified(AppointmentMessageKind::CONFIRMATION, Appointments::slot('2026-10-22 07:30'), Appointments::ROUSSEAU, Appointments::utc('2026-10-21 07:32'), Appointments::LEFEVRE);
        $this->states->save($state);
        $this->clock->at('2026-10-21 07:45');
        $this->remind();

        self::assertSame(['+33698765432'], array_map(static fn (AppointmentMessage $m): string => $m->recipientAddress, $this->notifications->messages));
    }

    private function plan(Appointment $appointment, ReminderDelay $delay = ReminderDelay::H24, string $now = '2026-10-13 08:44'): void
    {
        $this->settings->delays[Appointments::CLINIC] = $delay;
        $this->appointments->save($appointment);
        $state = $this->states->lock($appointment->id());
        \assert(null !== $state);
        $state->planReminder($delay, $appointment->timeSlot()->startsAtUtc(), new \DateTimeZone('Europe/Paris'), Appointments::utc($now));
        $this->states->save($state);
    }

    private function remind(string $appointmentId = Appointments::ID): void
    {
        $clinics = new FakeClinicDirectory(printedNames: [Appointments::ROUSSEAU => 'Dr Rousseau']);

        new RequestAppointmentReminderHandler(
            $this->states,
            $this->appointments,
            $this->settings,
            $clinics,
            $this->contacts,
            new AppointmentMessageComposer(new FakeAnimals(), $clinics, new IcsWriter(), new InMemoryAttachments()),
            $this->notifications,
            $this->clock,
        )(new RequestAppointmentReminder(AppointmentId::fromString($appointmentId)->toString()));
    }
}
