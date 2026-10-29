<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\SyncAppointmentNotification;

use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotificationHandler;
use App\Context\Scheduling\Application\Notification\AppointmentMessageComposer;
use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Calendar\IcsWriter;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingNotifications;
use App\Tests\Unit\Context\Scheduling\Support\FakeAnimals;
use App\Tests\Unit\Context\Scheduling\Support\FakeClientContacts;
use App\Tests\Unit\Context\Scheduling\Support\FakeClinicDirectory;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAttachments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryNotificationStates;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryReminderSettings;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * D2: each sync derives the one message an appointment calls for from the
 * appointment as it is and what its client was last told.
 */
final class SyncAppointmentNotificationHandlerTest extends TestCase
{
    private InMemoryAppointments $appointments;
    private InMemoryNotificationStates $states;
    private InMemoryReminderSettings $settings;
    private FakeClinicDirectory $clinics;
    private FakeClientContacts $contacts;
    private FakeAnimals $animals;
    private InMemoryAttachments $attachments;
    private CapturingNotifications $notifications;
    private MovableClock $clock;

    protected function setUp(): void
    {
        $this->appointments  = new InMemoryAppointments();
        $this->states        = new InMemoryNotificationStates($this->appointments);
        $this->settings      = new InMemoryReminderSettings();
        $this->clinics       = new FakeClinicDirectory(printedNames: [Appointments::ROUSSEAU => 'Dr Rousseau', Appointments::LAMBERT => 'Dr Claire Lambert']);
        $this->contacts      = new FakeClientContacts();
        $this->animals       = new FakeAnimals();
        $this->attachments   = new InMemoryAttachments();
        $this->notifications = new CapturingNotifications();
        // Booked on Tuesday 13/10 at 10:42 Paris, synced two minutes later.
        $this->clock = new MovableClock(Appointments::utc('2026-10-13 08:44'));
    }

    public function testABookingIsConfirmedByEmailWithACalendarInvitation(): void
    {
        $this->sync(Appointments::planned());

        // AC 12: one e-mail, « Rendez-vous confirmé — Milo, jeudi 22 octobre à 9h30 » with an .ics REQUEST, SEQUENCE:0.
        $message = $this->onlyMessage();
        self::assertSame([AppointmentMessage::CONFIRMATION, ClientContact::EMAIL, 's.dubois@email.fr', 'Sophie Dubois'], [$message->purpose, $message->channel, $message->recipientAddress, $message->recipientName]);
        self::assertSame([Appointments::CLINIC, Appointments::OWNER, Appointments::ID], [$message->clinicId, $message->clientId, $message->appointmentId]);
        self::assertSame('appointment:' . Appointments::ID . ':confirmation:1', $message->idempotencyKey);
        self::assertSame([
            'addressee'        => 'Madame Dubois',
            'animalName'       => 'Milo',
            'subjectDateLabel' => 'jeudi 22 octobre à 9h30',
            'dateLabel'        => 'Jeudi 22 octobre 2026',
            'timeLabel'        => '9h30 · 30 min',
            'reason'           => 'Vaccination',
            'practitionerName' => 'Dr Rousseau',
        ], $message->parameters);

        $ics = $this->ics($message);
        self::assertStringContainsString("METHOD:REQUEST\r\n", $ics);
        self::assertStringContainsString("SEQUENCE:0\r\n", $ics);
        self::assertStringContainsString('UID:' . Appointments::ID . "@kiveto.app\r\n", $ics);
        self::assertStringContainsString("DTSTART:20261022T073000Z\r\n", $ics);
        self::assertNotNull($message->attachment);
        self::assertSame(['rendez-vous.ics', 'text/calendar; method=REQUEST'], [$message->attachment->filename, $message->attachment->mime]);
        self::assertNull($message->fallbackAddress);

        // The reminder is planned (24 h before by default), the state recorded.
        $state = $this->states->of(Appointments::ID);
        self::assertSame('2026-10-21 07:30', $state->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame(1, $state->notificationCounter());
    }

    public function testAClientWhoPrefersSmsIsConfirmedBySms(): void
    {
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', transactionalChannel: ClientContact::SMS);

        $this->sync(Appointments::planned());

        // AC 12: « …RDV confirmé pour Milo le jeudi 22/10 à 9h30 avec le Dr Rousseau… ».
        $message = $this->onlyMessage();
        self::assertSame([ClientContact::SMS, '+33612345678', null], [$message->channel, $message->recipientAddress, $message->attachment]);
        self::assertSame(['animalName' => 'Milo', 'slotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => 'Dr Rousseau'], $message->parameters);
    }

    public function testNothingIsSentWhenTheOwnerCannotBeReached(): void
    {
        // AC 13: no owner, an owner without address, an archived or unknown one.
        $this->sync(Appointments::planned(ownerId: null));
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(email: null, transactionalChannel: null);
        $this->sync(Appointments::planned());
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(archived: true);
        $this->sync(Appointments::planned());
        unset($this->contacts->contacts[Appointments::OWNER]);
        $this->sync(Appointments::planned());

        self::assertSame([], $this->notifications->messages);
        self::assertNull($this->states->of(Appointments::ID)->confirmedAt());

        // Reachable at a later sync: the confirmation goes then.
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie();
        $this->sync(Appointments::planned());
        self::assertSame(AppointmentMessage::CONFIRMATION, $this->onlyMessage()->purpose);
    }

    public function testOnlyAChangeOfTimeOrPractitionerIsTold(): void
    {
        $this->sync(Appointments::planned());

        // AC 14: a new motif changes nothing; a new time is told, with the old and new slots and SEQUENCE:1.
        $this->sync(Appointments::planned(reason: 'Rappel de vaccin', duration: 45));
        self::assertCount(1, $this->notifications->messages);

        $this->clock->at('2026-10-14 16:05');
        $this->sync(Appointments::planned('2026-10-23 09:00'));

        $change = $this->notifications->last();
        self::assertCount(2, $this->notifications->messages);
        self::assertSame([AppointmentMessage::CHANGE, 'appointment:' . Appointments::ID . ':change:2'], [$change->purpose, $change->idempotencyKey]);
        self::assertSame([
            'addressee'         => 'Madame Dubois',
            'animalName'        => 'Milo',
            'subjectDateLabel'  => 'vendredi 23 octobre à 11h00',
            'previousSlotLabel' => 'Jeu. 22 oct. · 9h30',
            'newSlotLabel'      => 'Ven. 23 oct. · 11h00',
            'reason'            => 'Vaccination',
            'practitionerName'  => 'Dr Rousseau',
        ], $change->parameters);
        self::assertStringContainsString("SEQUENCE:1\r\n", $this->ics($change));
        self::assertStringContainsString("DTSTART:20261023T090000Z\r\n", $this->ics($change));
        // The reminder follows the new slot (AC 21).
        self::assertSame('2026-10-22 09:00', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
    }

    public function testAChangeBySmsQuotesTheSlotTheClientKnew(): void
    {
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', transactionalChannel: ClientContact::SMS);
        $this->sync(Appointments::planned());

        // AC 16: time and veterinarian changed together — one change message.
        $this->sync(Appointments::planned('2026-10-23 09:00', practitioner: Appointments::LAMBERT));

        self::assertCount(2, $this->notifications->messages);
        self::assertSame(['animalName' => 'Milo', 'slotLabel' => 'vendredi 23/10 à 11h00', 'previousSlotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => 'Dr Claire Lambert'], $this->notifications->last()->parameters);
    }

    public function testMovesWithinTheWindowAreToldOnceAsTheyEndedUp(): void
    {
        $this->sync(Appointments::planned());

        // AC 15: moved and moved back (agenda undo) → nothing; moved three times → one change, the final slot.
        $this->sync(Appointments::planned());
        self::assertCount(1, $this->notifications->messages);

        $this->sync(Appointments::planned('2026-10-23 12:00'));
        $this->sync(Appointments::planned('2026-10-23 12:00'));
        $this->sync(Appointments::planned('2026-10-23 12:00'));

        self::assertCount(2, $this->notifications->messages);
        self::assertSame('Ven. 23 oct. · 14h00', $this->notifications->last()->parameters['newSlotLabel']);
    }

    public function testABookingMovedOrCancelledWithinTheWindow(): void
    {
        // AC 15b: booked then moved → one confirmation of the final slot; booked then cancelled → nothing.
        $this->sync(Appointments::planned('2026-10-23 12:00'));
        self::assertSame([AppointmentMessage::CONFIRMATION, 'vendredi 23 octobre à 14h00'], [$this->onlyMessage()->purpose, $this->onlyMessage()->parameters['subjectDateLabel']]);

        $this->sync(Appointments::planned(id: '01960000-0000-7000-8000-0000000a0002', status: AppointmentStatus::CANCELLED));
        self::assertCount(1, $this->notifications->messages);
    }

    public function testAnUpcomingCancellationIsToldWithACalendarCancellation(): void
    {
        $this->sync(Appointments::planned());
        $this->clock->at('2026-10-15 07:12');

        $this->sync(Appointments::planned('2026-10-23 12:00', practitioner: Appointments::LAMBERT, status: AppointmentStatus::CANCELLED));

        // AC 17: the slot the client knows, METHOD:CANCEL; its reminder is dropped.
        $cancellation = $this->notifications->last();
        self::assertSame([AppointmentMessage::CANCELLATION, 'appointment:' . Appointments::ID . ':cancellation:2'], [$cancellation->purpose, $cancellation->idempotencyKey]);
        self::assertSame([
            'addressee'        => 'Madame Dubois',
            'animalName'       => 'Milo',
            'subjectDateLabel' => 'jeudi 22 octobre à 9h30',
            'dateLabel'        => 'Jeudi 22 octobre 2026',
            'timeLabel'        => '9h30',
            'reason'           => 'Vaccination',
            'practitionerName' => 'Dr Rousseau',
        ], $cancellation->parameters);
        $ics = $this->ics($cancellation);
        self::assertStringContainsString("METHOD:CANCEL\r\n", $ics);
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $ics);
        self::assertStringContainsString("SEQUENCE:1\r\n", $ics);
        self::assertSame('text/calendar; method=CANCEL', $cancellation->attachment?->mime);
        self::assertNull($this->states->of(Appointments::ID)->reminderDueAt());

        // Told once.
        $this->sync(Appointments::planned(status: AppointmentStatus::CANCELLED));
        self::assertCount(2, $this->notifications->messages);
    }

    public function testACancellationBySms(): void
    {
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(mobile: '+33612345678', transactionalChannel: ClientContact::SMS);
        $this->sync(Appointments::planned());

        $this->sync(Appointments::planned(status: AppointmentStatus::CANCELLED));

        self::assertSame(['animalName' => 'Milo', 'slotLabel' => 'jeudi 22/10 à 9h30'], $this->notifications->last()->parameters);
    }

    public function testNoShowsAndSilencedCancellationsAreNotTold(): void
    {
        $this->sync(Appointments::planned());

        // AC 17: a past appointment marked no-show.
        $this->clock->at('2026-10-22 08:30');
        $this->sync(Appointments::planned(status: AppointmentStatus::NO_SHOW));

        // AC 18: the death of the animal silenced the cancellation.
        $this->clock->at('2026-10-13 09:00');
        $other = '01960000-0000-7000-8000-0000000a0002';
        $this->sync(Appointments::planned(id: $other));
        $this->states->silence(AppointmentId::fromString($other), ClinicId::fromString(Appointments::CLINIC));
        $this->sync(Appointments::planned(id: $other, status: AppointmentStatus::CANCELLED));

        self::assertSame([AppointmentMessage::CONFIRMATION, AppointmentMessage::CONFIRMATION], array_map(static fn (AppointmentMessage $m): string => $m->purpose, $this->notifications->messages));
    }

    public function testAnOwnerSetAfterTheBookingIsConfirmed(): void
    {
        $this->sync(Appointments::planned(ownerId: null, animalId: null));
        self::assertSame([], $this->notifications->messages);

        $this->clock->at('2026-10-14 10:00');
        $this->sync(Appointments::planned());

        self::assertSame([AppointmentMessage::CONFIRMATION, Appointments::OWNER, 'appointment:' . Appointments::ID . ':confirmation:1'], [$this->onlyMessage()->purpose, $this->onlyMessage()->clientId, $this->onlyMessage()->idempotencyKey]);
    }

    public function testAnotherOwnerIsConfirmedAndTheFormerOneToldOfTheCancellation(): void
    {
        $this->sync(Appointments::planned());

        // The booking was Marc Lefèvre's, moved to Friday at the same time.
        $this->clock->at('2026-10-14 10:00');
        $this->sync(Appointments::planned('2026-10-23 09:00', ownerId: Appointments::LEFEVRE, animalId: '01960000-0000-7000-8000-0000000f0002'));

        self::assertCount(3, $this->notifications->messages);
        [, $cancellation, $confirmation] = $this->notifications->messages;

        // Sophie hears the slot she knew is cancelled (her calendar drops it), without the new animal's name.
        self::assertSame([AppointmentMessage::CANCELLATION, Appointments::OWNER, 's.dubois@email.fr', 'appointment:' . Appointments::ID . ':cancellation:2'], [$cancellation->purpose, $cancellation->clientId, $cancellation->recipientAddress, $cancellation->idempotencyKey]);
        self::assertSame(['votre animal', 'jeudi 22 octobre à 9h30'], [$cancellation->parameters['animalName'], $cancellation->parameters['subjectDateLabel']]);
        self::assertStringContainsString("METHOD:CANCEL\r\n", $this->ics($cancellation));

        // Marc gets the confirmation of the current slot, on his channel.
        self::assertSame([AppointmentMessage::CONFIRMATION, Appointments::LEFEVRE, ClientContact::SMS, 'appointment:' . Appointments::ID . ':confirmation:3'], [$confirmation->purpose, $confirmation->clientId, $confirmation->channel, $confirmation->idempotencyKey]);
        self::assertSame('vendredi 23/10 à 11h00', $confirmation->parameters['slotLabel']);
        self::assertSame(Appointments::LEFEVRE, $this->states->of(Appointments::ID)->notifiedOwnerId());

        // Told once; a later cancellation goes to Marc only.
        $this->sync(Appointments::planned('2026-10-23 09:00', ownerId: Appointments::LEFEVRE));
        $this->sync(Appointments::planned('2026-10-23 09:00', ownerId: Appointments::LEFEVRE, status: AppointmentStatus::CANCELLED));
        self::assertCount(4, $this->notifications->messages);
        self::assertSame([AppointmentMessage::CANCELLATION, Appointments::LEFEVRE, 'appointment:' . Appointments::ID . ':cancellation:4'], [$this->notifications->last()->purpose, $this->notifications->last()->clientId, $this->notifications->last()->idempotencyKey]);
    }

    public function testAnOwnerChangeUndoneWithinTheWindowSaysNothing(): void
    {
        $this->sync(Appointments::planned());

        // Another client picked by mistake then put back before the sync: the sync sees Sophie again.
        $this->appointments->save(Appointments::planned(ownerId: Appointments::LEFEVRE));
        $this->sync(Appointments::planned());

        self::assertCount(1, $this->notifications->messages);
    }

    public function testAnOwnerRemovedIsToldOfTheCancellation(): void
    {
        $this->sync(Appointments::planned());

        $this->sync(Appointments::planned(ownerId: null, animalId: null));

        self::assertCount(2, $this->notifications->messages);
        self::assertSame([AppointmentMessage::CANCELLATION, Appointments::OWNER], [$this->notifications->last()->purpose, $this->notifications->last()->clientId]);
        self::assertNull($this->states->of(Appointments::ID)->confirmedAt());
    }

    public function testACancellationAfterAnOwnerChangeGoesToTheClientWhoWasTold(): void
    {
        $this->sync(Appointments::planned());

        // Given to Marc and cancelled within the window: Marc never knew, Sophie did.
        $this->sync(Appointments::planned(ownerId: Appointments::LEFEVRE, status: AppointmentStatus::CANCELLED));

        self::assertCount(2, $this->notifications->messages);
        self::assertSame([AppointmentMessage::CANCELLATION, Appointments::OWNER, 'votre animal'], [$this->notifications->last()->purpose, $this->notifications->last()->clientId, $this->notifications->last()->parameters['animalName']]);
    }

    public function testAFormerOwnerWhoCannotBeReachedIsSkipped(): void
    {
        $this->sync(Appointments::planned());
        $this->contacts->contacts[Appointments::OWNER] = FakeClientContacts::sophie(archived: true);

        $this->sync(Appointments::planned(ownerId: Appointments::LEFEVRE));

        self::assertCount(2, $this->notifications->messages);
        self::assertSame([AppointmentMessage::CONFIRMATION, Appointments::LEFEVRE, 'appointment:' . Appointments::ID . ':confirmation:2'], [$this->notifications->last()->purpose, $this->notifications->last()->clientId, $this->notifications->last()->idempotencyKey]);
    }

    public function testAnUnknownAppointmentIsIgnored(): void
    {
        $this->handler()(new SyncAppointmentNotification(Appointments::ID));

        self::assertSame([], $this->notifications->messages);
        self::assertSame([], $this->states->states);
    }

    public function testDefaultsWhenTheMotifAnimalOrPractitionerNameIsMissing(): void
    {
        $this->sync(Appointments::planned(practitioner: '01960000-0000-7000-8000-0000000d0009', animalId: null, reason: '  '));

        $parameters = $this->onlyMessage()->parameters;
        self::assertSame(['votre animal', 'Consultation', ''], [$parameters['animalName'], $parameters['reason'], $parameters['practitionerName']]);
        self::assertStringNotContainsString('Vétérinaire', $this->ics($this->onlyMessage()));
    }

    public function testLabelsAndDueTimesFollowTheClinicTimeZone(): void
    {
        // AC 22: Monday 26/10/2026 09:30 in Cayenne (12:30 UTC).
        $this->clinics->timeZone                      = 'America/Cayenne';
        $this->settings->delays[Appointments::CLINIC] = \App\Context\Scheduling\Domain\Notification\ReminderDelay::H48;

        $this->sync(Appointments::planned('2026-10-26 12:30'));

        self::assertSame('lundi 26 octobre à 9h30', $this->onlyMessage()->parameters['subjectDateLabel']);
        self::assertSame('2026-10-24 12:30', $this->states->of(Appointments::ID)->reminderDueAt()?->format('Y-m-d H:i'));
    }

    private function sync(Appointment $appointment): void
    {
        $this->appointments->save($appointment);
        $this->handler()(new SyncAppointmentNotification($appointment->id()->toString()));
    }

    private function handler(): SyncAppointmentNotificationHandler
    {
        return new SyncAppointmentNotificationHandler(
            $this->states,
            $this->appointments,
            $this->settings,
            $this->clinics,
            $this->contacts,
            new AppointmentMessageComposer($this->animals, $this->clinics, new IcsWriter(), $this->attachments),
            $this->notifications,
            $this->clock,
            new NullLogger(),
        );
    }

    private function onlyMessage(): AppointmentMessage
    {
        self::assertCount(1, $this->notifications->messages);

        return $this->notifications->messages[0];
    }

    private function ics(AppointmentMessage $message): string
    {
        self::assertNotNull($message->attachment);

        return $this->attachments->files[$message->attachment->storageKey] ?? '';
    }
}
