<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Adapter\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command, keyed per token: a replayed request never
 * sends a second e-mail for the same link.
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    private const string SUBJECT_USER = 'user';

    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function requestStaffInvitation(StaffInvitationRequest $request): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'STAFF_INVITATION',
            channel: 'EMAIL',
            recipientAddress: $request->email,
            recipientName: $request->firstName,
            clinicId: $request->clinicId,
            clientId: null,
            subjectType: self::SUBJECT_USER,
            subjectId: $request->userId,
            idempotencyKey: 'invitation:' . $request->tokenId,
            parameters: [
                'firstName'     => $request->firstName,
                'inviterName'   => $request->inviterName,
                'clinicName'    => $request->clinicName,
                'clinicCity'    => $request->clinicCity,
                'roleLabel'     => $request->roleLabel,
                'activationUrl' => $request->activationUrl,
            ],
        ));
    }

    public function requestPasswordReset(PasswordResetRequest $request): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PASSWORD_RESET',
            channel: 'EMAIL',
            recipientAddress: $request->email,
            recipientName: '',
            clinicId: null,
            clientId: null,
            subjectType: self::SUBJECT_USER,
            subjectId: $request->userId,
            idempotencyKey: 'password-reset:' . $request->tokenId,
            parameters: [
                'accountEmail'     => $request->email,
                'resetUrl'         => $request->resetUrl,
                'requestedAtLabel' => $request->requestedAtLabel,
            ],
        ));
    }
}
