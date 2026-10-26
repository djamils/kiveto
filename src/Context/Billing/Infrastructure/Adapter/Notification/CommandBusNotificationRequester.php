<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\InvoiceMessage;
use App\Context\Billing\Application\Port\NotificationRequesterInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\EmailFallback;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command, in the transaction of the caller: the
 * message is recorded with the dispatch, or not at all. The invoice PDF
 * stays Billing's (`owner = REQUESTER`): Notification never deletes it.
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    private const string SUBJECT_INVOICE = 'invoice';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function request(InvoiceMessage $message): string
    {
        $attachment = $message->attachment;

        $id = $this->commandBus->dispatch(new RequestNotification(
            purpose: $message->purpose,
            channel: $message->channel,
            recipientAddress: $message->recipientAddress,
            recipientName: $message->recipientName,
            clinicId: $message->clinicId,
            clientId: $message->clientId,
            subjectType: self::SUBJECT_INVOICE,
            subjectId: $message->invoiceId,
            idempotencyKey: $message->idempotencyKey,
            parameters: $message->parameters,
            attachments: null !== $attachment
                ? [new AttachmentReference($attachment->storageKey, $attachment->filename, SentDocumentFile::MIME, AttachmentReference::OWNER_REQUESTER)]
                : [],
            fallback: null !== $message->fallbackAddress && null !== $message->fallbackParameters
                ? new EmailFallback($message->fallbackAddress, $message->recipientName, $message->fallbackParameters)
                : null,
        ));

        \assert(\is_string($id));

        return $id;
    }
}
