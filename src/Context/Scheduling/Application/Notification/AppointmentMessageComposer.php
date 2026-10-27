<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Notification;

use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\AttachmentStoreInterface;
use App\Context\Scheduling\Application\Port\CalendarInvitation;
use App\Context\Scheduling\Application\Port\CalendarInvitationWriterInterface;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClinicCard;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\StoredAttachment;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Notification\AppointmentMessageKind;
use App\Context\Scheduling\Domain\Notification\NotifiedSlot;

/**
 * Words the client messages of an appointment: the parameters of the
 * Notification template of each purpose and channel (labels in the clinic's
 * time zone, « Consultation » when there is no motif, no practitioner line
 * when the practitioner has no printed name) and, for an e-mail, the
 * calendar file — an invitation with a stable uid whose sequence grows with
 * each message, or its cancellation.
 */
final readonly class AppointmentMessageComposer
{
    public const string ICS_FILENAME = 'rendez-vous.ics';
    public const string ICS_MIME     = 'text/calendar';

    private const string DEFAULT_REASON = 'Consultation';
    private const string UNKNOWN_ANIMAL = 'votre animal';
    private const string UID_DOMAIN     = 'kiveto.fr';

    public function __construct(
        private AnimalDirectoryInterface $animals,
        private ClinicDirectoryInterface $clinics,
        private CalendarInvitationWriterInterface $calendar,
        private AttachmentStoreInterface $attachments,
    ) {
    }

    /**
     * The confirmation, change or cancellation numbered $number on the
     * contact's transactional channel. A confirmation or a change shows the
     * appointment as it is; a change also shows $notified, the slot the client
     * knew; a cancellation shows that notified slot (the one the client knows),
     * also to a former owner of the booking.
     */
    public function transactional(
        AppointmentMessageKind $kind,
        int $number,
        Appointment $appointment,
        ?NotifiedSlot $notified,
        ClientContact $contact,
        string $channel,
        ClinicCard $clinic,
        \DateTimeImmutable $now,
    ): AppointmentMessage {
        $clinicId = $appointment->clinicId()->toString();
        $key      = \sprintf('appointment:%s:%s:%d', $appointment->id()->toString(), $kind->value, $number);

        $current = new NotifiedSlot(
            $appointment->timeSlot()->startsAtUtc(),
            $appointment->timeSlot()->durationMinutes(),
            $appointment->practitionerAssignee()->userId()->toString(),
        );
        $shown = AppointmentMessageKind::CANCELLATION === $kind && null !== $notified ? $notified : $current;

        $local = $shown->startsAtUtc->setTimezone($clinic->timeZone);
        // A former owner (the booking was given to another client) is not told the new animal's name.
        $animal       = $contact->clientId === $appointment->ownerId() ? $this->animalName($appointment) : self::UNKNOWN_ANIMAL;
        $reason       = $this->reason($appointment);
        $practitioner = $this->clinics->printedName($clinicId, $shown->practitionerUserId) ?? '';
        $email        = ClientContact::EMAIL === $channel;
        $previous     = ($notified ?? $current)->startsAtUtc->setTimezone($clinic->timeZone);

        $parameters = match ($kind) {
            AppointmentMessageKind::CONFIRMATION => $email
                ? ['addressee' => $contact->addressee, 'animalName' => $animal, 'subjectDateLabel' => AppointmentLabels::subjectDate($local), 'dateLabel' => AppointmentLabels::date($local), 'timeLabel' => AppointmentLabels::timeAndDuration($local, $shown->durationMinutes), 'reason' => $reason, 'practitionerName' => $practitioner]
                : ['animalName' => $animal, 'slotLabel' => AppointmentLabels::slot($local), 'practitionerName' => $practitioner],
            AppointmentMessageKind::CHANGE => $email
                ? ['addressee' => $contact->addressee, 'animalName' => $animal, 'subjectDateLabel' => AppointmentLabels::subjectDate($local), 'previousSlotLabel' => AppointmentLabels::shortSlot($previous), 'newSlotLabel' => AppointmentLabels::shortSlot($local), 'reason' => $reason, 'practitionerName' => $practitioner]
                : ['animalName' => $animal, 'slotLabel' => AppointmentLabels::slot($local), 'previousSlotLabel' => AppointmentLabels::slot($previous), 'practitionerName' => $practitioner],
            AppointmentMessageKind::CANCELLATION => $email
                ? ['addressee' => $contact->addressee, 'animalName' => $animal, 'subjectDateLabel' => AppointmentLabels::subjectDate($local), 'dateLabel' => AppointmentLabels::date($local), 'timeLabel' => AppointmentLabels::time($local), 'reason' => $reason, 'practitionerName' => $practitioner]
                : ['animalName' => $animal, 'slotLabel' => AppointmentLabels::slot($local)],
        };

        $attachment = $email
            ? $this->invitation($key, $number, AppointmentMessageKind::CANCELLATION === $kind, $appointment, $shown, $animal, $reason, $practitioner, $contact, $clinic, $now)
            : null;

        return new AppointmentMessage(
            purpose: match ($kind) {
                AppointmentMessageKind::CONFIRMATION => AppointmentMessage::CONFIRMATION,
                AppointmentMessageKind::CHANGE       => AppointmentMessage::CHANGE,
                AppointmentMessageKind::CANCELLATION => AppointmentMessage::CANCELLATION,
            },
            channel: $channel,
            recipientAddress: (string) ($email ? $contact->email : $contact->mobile),
            recipientName: $contact->name,
            clinicId: $clinicId,
            clientId: $contact->clientId,
            appointmentId: $appointment->id()->toString(),
            idempotencyKey: $key,
            parameters: $parameters,
            attachment: $attachment,
        );
    }

    /**
     * The reminder SMS (one per appointment, ever), with the e-mail sent
     * instead when the clinic's SMS cap holds it back — only when the
     * client's reminder consent also allows e-mail.
     */
    public function reminder(Appointment $appointment, ClientContact $contact, ClinicCard $clinic, \DateTimeImmutable $now): AppointmentMessage
    {
        $clinicId     = $appointment->clinicId()->toString();
        $local        = $appointment->timeSlot()->startsAtUtc()->setTimezone($clinic->timeZone);
        $animal       = $this->animalName($appointment);
        $when         = AppointmentLabels::when($local, $now->setTimezone($clinic->timeZone));
        $practitioner = $this->clinics->printedName($clinicId, $appointment->practitionerAssignee()->userId()->toString()) ?? '';
        $fallback     = null !== $contact->email && $contact->acceptsReminderOn(ClientContact::EMAIL);

        return new AppointmentMessage(
            purpose: AppointmentMessage::REMINDER,
            channel: ClientContact::SMS,
            recipientAddress: (string) $contact->mobile,
            recipientName: $contact->name,
            clinicId: $clinicId,
            clientId: $contact->clientId,
            appointmentId: $appointment->id()->toString(),
            idempotencyKey: \sprintf('appointment:%s:reminder', $appointment->id()->toString()),
            parameters: ['animalName' => $animal, 'whenLabel' => $when, 'practitionerName' => $practitioner],
            fallbackAddress: $fallback ? $contact->email : null,
            fallbackParameters: $fallback
                ? ['addressee' => $contact->addressee, 'animalName' => $animal, 'whenLabel' => $when, 'practitionerName' => $practitioner]
                : null,
        );
    }

    private function invitation(
        string $key,
        int $number,
        bool $cancelled,
        Appointment $appointment,
        NotifiedSlot $shown,
        string $animal,
        string $reason,
        string $practitioner,
        ClientContact $contact,
        ClinicCard $clinic,
        \DateTimeImmutable $now,
    ): StoredAttachment {
        $ics = $this->calendar->write(new CalendarInvitation(
            uid: $appointment->id()->toString() . '@' . self::UID_DOMAIN,
            sequence: $number - 1,
            cancelled: $cancelled,
            startsAtUtc: $shown->startsAtUtc,
            durationMinutes: $shown->durationMinutes,
            summary: \sprintf('Rendez-vous de %s — %s', $animal, $clinic->name),
            description: 'Motif : ' . $reason . ('' !== $practitioner ? "\nVétérinaire : " . $practitioner : ''),
            location: $clinic->address,
            organizerName: $clinic->name,
            organizerEmail: $clinic->email,
            attendeeName: $contact->name,
            attendeeEmail: (string) $contact->email,
            stampedAt: $now,
        ));

        return $this->attachments->store($key, self::ICS_FILENAME, self::ICS_MIME, $ics);
    }

    private function animalName(Appointment $appointment): string
    {
        $animalId = $appointment->animalId();

        return (null !== $animalId ? $this->animals->nameOf($appointment->clinicId()->toString(), $animalId) : null) ?? self::UNKNOWN_ANIMAL;
    }

    private function reason(Appointment $appointment): string
    {
        $reason = mb_trim($appointment->reason() ?? '');

        return '' !== $reason ? $reason : self::DEFAULT_REASON;
    }
}
