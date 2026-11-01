<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Notification;

use App\Context\ClientPortal\Application\Port\DataExportReadyMessage;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Application\Port\PortalInvitationMessage;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command. The client id makes Notification journal
 * the message in the client file (Client's OutboundMessageStatusChanged
 * consumer); each send has its own key, a resend being a new message. The
 * export notice belongs to no clinic nor client file: sent by Kiveto.
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function requestPortalInvitation(PortalInvitationMessage $message): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_INVITATION',
            channel: mb_strtoupper($message->channel),
            recipientAddress: $message->recipientAddress,
            recipientName: $message->recipientName,
            clinicId: $message->clinicId,
            clientId: $message->clientId,
            subjectType: 'portal_invitation',
            subjectId: $message->invitationId,
            idempotencyKey: \sprintf('portal-invitation:%s:%s', $message->invitationId, $message->sentAt->format('U')),
            parameters: [
                'clinicName'     => $message->clinicName,
                'ownerFirstName' => $message->ownerFirstName,
                'link'           => $message->link,
                'expiresOn'      => $message->expiresOn,
            ],
        ));
    }

    public function requestDataExportReady(DataExportReadyMessage $message): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_DATA_EXPORT_READY',
            channel: 'EMAIL',
            recipientAddress: $message->recipientEmail,
            recipientName: $message->recipientName,
            clinicId: null,
            clientId: null,
            subjectType: 'portal_data_export',
            subjectId: $message->exportId,
            idempotencyKey: 'portal-data-export-ready:' . $message->exportId,
            parameters: [
                'ownerFirstName' => $message->recipientFirstName,
                'expiresAtLabel' => $message->expiresAtLabel,
            ],
        ));
    }
}
