<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Notification;

use App\Context\Consultation\Application\Port\DocumentsMessage;
use App\Context\Consultation\Application\Port\NotificationRequesterInterface;
use App\Context\Consultation\Application\Port\StoredDocument;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command. The report and the ordonnances were
 * rendered for this e-mail (`owner = NOTIFICATION`, purged with the message
 * content); the invoice PDF stays Billing's (`owner = REQUESTER`).
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    private const string PURPOSE              = 'CONSULTATION_DOCUMENTS';
    private const string CHANNEL              = 'EMAIL';
    private const string SUBJECT_CONSULTATION = 'consultation';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function request(DocumentsMessage $message): string
    {
        $id = $this->commandBus->dispatch(new RequestNotification(
            purpose: self::PURPOSE,
            channel: self::CHANNEL,
            recipientAddress: $message->recipientAddress,
            recipientName: $message->recipientName,
            clinicId: $message->clinicId,
            clientId: $message->clientId,
            subjectType: self::SUBJECT_CONSULTATION,
            subjectId: $message->consultationId,
            idempotencyKey: $message->idempotencyKey,
            parameters: $message->parameters,
            attachments: [
                ...array_map(static fn (StoredDocument $file): AttachmentReference => self::reference($file, AttachmentReference::OWNER_NOTIFICATION), $message->sendOnlyFiles),
                ...array_map(static fn (StoredDocument $file): AttachmentReference => self::reference($file, AttachmentReference::OWNER_REQUESTER), $message->requesterFiles),
            ],
        ));

        \assert(\is_string($id));

        return $id;
    }

    private static function reference(StoredDocument $file, string $owner): AttachmentReference
    {
        return new AttachmentReference($file->storageKey, $file->filename, StoredDocument::MIME, $owner);
    }
}
