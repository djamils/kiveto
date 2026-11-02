<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Adapter\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailChangedNoticeRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailVerificationRequest;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;

/**
 * Notification's public command, keyed per token: a replayed request never
 * sends a second e-mail for the same link. The e-mail verification codes and
 * change notices carry their account (accountId): they go to addresses the
 * account may no longer have when it is deleted, and are masked with it.
 */
final readonly class CommandBusNotificationRequester implements NotificationRequesterInterface
{
    private const string SUBJECT_USER = 'user';
    private const string SUBJECT_OTP  = 'otp_challenge';

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

    public function requestPortalOtp(PortalOtpRequest $request): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_OTP',
            channel: 'SMS',
            recipientAddress: $request->phone,
            recipientName: '',
            clinicId: null,
            clientId: null,
            subjectType: self::SUBJECT_OTP,
            subjectId: $request->challengeId,
            idempotencyKey: \sprintf('otp:%s:%d', $request->challengeId, $request->sendCount),
            parameters: [
                'code'            => $request->code,
                'validityMinutes' => $request->validityMinutes,
                'purpose'         => $request->purpose,
            ],
        ));
    }

    /** Same key space as the SMS: each send of a challenge is one message, whatever its channel. */
    public function requestPortalOtpByEmail(PortalOtpRequest $request, string $email): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_OTP',
            channel: 'EMAIL',
            recipientAddress: $email,
            recipientName: '',
            clinicId: null,
            clientId: null,
            subjectType: self::SUBJECT_OTP,
            subjectId: $request->challengeId,
            idempotencyKey: \sprintf('otp:%s:%d', $request->challengeId, $request->sendCount),
            parameters: [
                'code'            => $request->code,
                'validityMinutes' => $request->validityMinutes,
            ],
        ));
    }

    public function requestPortalEmailVerification(PortalEmailVerificationRequest $request): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_EMAIL_VERIFICATION',
            channel: 'EMAIL',
            recipientAddress: $request->email,
            recipientName: '',
            clinicId: null,
            clientId: null,
            subjectType: self::SUBJECT_OTP,
            subjectId: $request->challengeId,
            idempotencyKey: \sprintf('otp:%s:1', $request->challengeId),
            parameters: [
                'code'            => $request->code,
                'validityMinutes' => $request->validityMinutes,
            ],
            accountId: $request->userId,
        ));
    }

    public function requestPortalEmailChangedNotice(PortalEmailChangedNoticeRequest $request): void
    {
        $this->commandBus->dispatch(new RequestNotification(
            purpose: 'PORTAL_EMAIL_CHANGED_NOTICE',
            channel: 'EMAIL',
            recipientAddress: $request->previousEmail,
            recipientName: '',
            clinicId: null,
            clientId: null,
            subjectType: self::SUBJECT_USER,
            subjectId: $request->userId,
            idempotencyKey: 'email-changed:' . $request->challengeId,
            parameters: [
                'maskedNewEmail' => $request->maskedNewEmail,
                'changedAtLabel' => $request->changedAtLabel,
            ],
            accountId: $request->userId,
        ));
    }
}
