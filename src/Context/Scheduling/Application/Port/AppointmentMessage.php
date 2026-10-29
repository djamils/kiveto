<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * One message about an appointment: its purpose (Notification's
 * APPOINTMENT_CONFIRMATION, APPOINTMENT_CHANGE, APPOINTMENT_CANCELLATION or
 * APPOINTMENT_REMINDER), one channel and the parameters its template prints.
 */
final readonly class AppointmentMessage
{
    public const string CONFIRMATION = 'APPOINTMENT_CONFIRMATION';
    public const string CHANGE       = 'APPOINTMENT_CHANGE';
    public const string CANCELLATION = 'APPOINTMENT_CANCELLATION';
    public const string REMINDER     = 'APPOINTMENT_REMINDER';

    /**
     * @param string                     $channel            ClientContact::EMAIL or ClientContact::SMS
     * @param array<string, string>      $parameters         exactly what the template of the purpose and channel declares
     * @param StoredAttachment|null      $attachment         e-mail only: the calendar invitation
     * @param array<string, string>|null $fallbackParameters the e-mail sent instead of a reminder SMS held back by the
     *                                                       clinic's SMS cap, to $fallbackAddress (null: none)
     */
    public function __construct(
        public string $purpose,
        public string $channel,
        public string $recipientAddress,
        public string $recipientName,
        public string $clinicId,
        public string $clientId,
        public string $appointmentId,
        public string $idempotencyKey,
        public array $parameters,
        public ?StoredAttachment $attachment = null,
        public ?string $fallbackAddress = null,
        public ?array $fallbackParameters = null,
    ) {
    }
}
