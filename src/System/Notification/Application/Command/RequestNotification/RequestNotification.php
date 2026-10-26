<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RequestNotification;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Send this message »: the single entry point of Notification. The handler
 * returns the id of the message (string), the existing one when the
 * idempotency key was already used.
 *
 * - purpose: APPOINTMENT_CONFIRMATION, APPOINTMENT_CHANGE, APPOINTMENT_CANCELLATION,
 *   APPOINTMENT_REMINDER, INVOICE_SENT, INVOICE_REMINDER, CONSULTATION_DOCUMENTS,
 *   STAFF_INVITATION, PASSWORD_RESET or TEST; criticality and sender follow from it.
 * - channel: EMAIL or SMS; the recipient address is an e-mail address or an E.164 number.
 * - clinicId: the clinic the message is sent on behalf of (required except
 *   for staff invitation, password reset and test); clientId: the client it
 *   is journaled for, if any.
 * - subjectType / subjectId: the business object (appointment, invoice, consultation, user…).
 * - idempotencyKey: an opaque key built by the requester, unique per message.
 * - parameters: exactly what the template of the purpose and channel declares
 *   (see TemplateCatalog), ready to print.
 * - attachments: e-mail only.
 * - fallback: for a non-critical SMS, the e-mail to send instead when the
 *   clinic's overage cap is reached (key « <idempotencyKey>:fallback »).
 */
final readonly class RequestNotification implements CommandInterface
{
    /**
     * @param array<string, scalar|list<scalar>> $parameters
     * @param list<AttachmentReference>          $attachments
     */
    public function __construct(
        public string $purpose,
        public string $channel,
        public string $recipientAddress,
        public string $recipientName,
        public ?string $clinicId,
        public ?string $clientId,
        public string $subjectType,
        public string $subjectId,
        public string $idempotencyKey,
        public array $parameters,
        public array $attachments = [],
        public ?EmailFallback $fallback = null,
    ) {
    }
}
