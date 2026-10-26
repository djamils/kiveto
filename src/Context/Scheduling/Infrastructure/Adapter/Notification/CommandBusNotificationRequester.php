<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Notification;

use App\Context\Scheduling\Application\Port\AppointmentMessage;
use App\Context\Scheduling\Application\Port\NotificationRequesterInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\EmailFallback;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command, in the transaction of the caller: the
 * message is recorded with the appointment's notification state, or not at
 * all. The calendar file belongs to the message (deleted with its content).
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    private const string SUBJECT_APPOINTMENT = 'appointment';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function request(AppointmentMessage $message): void
    {
        $attachment = $message->attachment;

        $this->commandBus->dispatch(new RequestNotification(
            purpose: $message->purpose,
            channel: $message->channel,
            recipientAddress: $message->recipientAddress,
            recipientName: $message->recipientName,
            clinicId: $message->clinicId,
            clientId: $message->clientId,
            subjectType: self::SUBJECT_APPOINTMENT,
            subjectId: $message->appointmentId,
            idempotencyKey: $message->idempotencyKey,
            parameters: $message->parameters,
            attachments: null !== $attachment
                ? [new AttachmentReference($attachment->storageKey, $attachment->filename, $attachment->mime, AttachmentReference::OWNER_NOTIFICATION)]
                : [],
            fallback: null !== $message->fallbackAddress && null !== $message->fallbackParameters
                ? new EmailFallback($message->fallbackAddress, $message->recipientName, $message->fallbackParameters)
                : null,
        ));
    }
}
