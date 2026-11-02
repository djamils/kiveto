<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Adapter\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailChangedNoticeRequest;
use App\System\IdentityAccess\Application\Port\PortalEmailVerificationRequest;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Port\StaffInvitationRequest;
use App\System\IdentityAccess\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use PHPUnit\Framework\TestCase;

final class CommandBusNotificationRequesterTest extends TestCase
{
    public function testTheAccountEmailsAreRequestedWithTheTemplateParameters(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return 'message-id';
            }
        };
        $requester = new CommandBusNotificationRequester($bus);

        $requester->requestStaffInvitation(new StaffInvitationRequest('tok-1', 'user-1', 'hugo@example.com', 'clinic-1', 'Hugo', 'Dr Claire Lambert', 'Clinique Montmartre', 'Paris', 'Vétérinaire', 'https://x/invitation/s'));
        $requester->requestPasswordReset(new PasswordResetRequest('tok-2', 'user-2', 'claire@example.com', 'https://x/reinitialiser/s', '26/10/2026 à 08:14'));
        $requester->requestPortalOtp(new PortalOtpRequest('challenge-1', '+33612345678', '042137', 2, 10, 'account_deletion'));
        $requester->requestPortalOtpByEmail(new PortalOtpRequest('challenge-1', '+33612345678', '042138', 3, 10, 'login'), 'sophie@example.com');
        $requester->requestPortalEmailVerification(new PortalEmailVerificationRequest('challenge-2', 'user-3', 'new@example.com', '042139', 10));
        $requester->requestPortalEmailChangedNotice(new PortalEmailChangedNoticeRequest('challenge-2', 'user-3', 'old@example.com', 'n***@example.com', '01/11/2026 à 14:05'));

        [$invitation, $reset, $otp, $emailedOtp, $verification, $notice] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $invitation);
        self::assertSame(['STAFF_INVITATION', 'EMAIL', 'hugo@example.com', 'Hugo', 'clinic-1', null, 'user', 'user-1', 'invitation:tok-1'], [
            $invitation->purpose, $invitation->channel, $invitation->recipientAddress, $invitation->recipientName, $invitation->clinicId, $invitation->clientId, $invitation->subjectType, $invitation->subjectId, $invitation->idempotencyKey,
        ]);
        self::assertSame(['firstName' => 'Hugo', 'inviterName' => 'Dr Claire Lambert', 'clinicName' => 'Clinique Montmartre', 'clinicCity' => 'Paris', 'roleLabel' => 'Vétérinaire', 'activationUrl' => 'https://x/invitation/s'], $invitation->parameters);

        self::assertInstanceOf(RequestNotification::class, $reset);
        self::assertSame(['PASSWORD_RESET', 'EMAIL', 'claire@example.com', null, 'user-2', 'password-reset:tok-2'], [$reset->purpose, $reset->channel, $reset->recipientAddress, $reset->clinicId, $reset->subjectId, $reset->idempotencyKey]);
        self::assertSame(['accountEmail' => 'claire@example.com', 'resetUrl' => 'https://x/reinitialiser/s', 'requestedAtLabel' => '26/10/2026 à 08:14'], $reset->parameters);

        // One message per code: the n-th send of a challenge has its own key.
        self::assertInstanceOf(RequestNotification::class, $otp);
        self::assertSame(['PORTAL_OTP', 'SMS', '+33612345678', '', null, null, 'otp_challenge', 'challenge-1', 'otp:challenge-1:2'], [
            $otp->purpose, $otp->channel, $otp->recipientAddress, $otp->recipientName, $otp->clinicId, $otp->clientId, $otp->subjectType, $otp->subjectId, $otp->idempotencyKey,
        ]);
        self::assertSame(['code' => '042137', 'validityMinutes' => 10, 'purpose' => 'account_deletion'], $otp->parameters, 'the wording follows the purpose');

        // A sign-in code resent by e-mail shares the key space of the SMS.
        self::assertInstanceOf(RequestNotification::class, $emailedOtp);
        self::assertSame(['PORTAL_OTP', 'EMAIL', 'sophie@example.com', null, 'otp_challenge', 'challenge-1', 'otp:challenge-1:3'], [
            $emailedOtp->purpose, $emailedOtp->channel, $emailedOtp->recipientAddress, $emailedOtp->clinicId, $emailedOtp->subjectType, $emailedOtp->subjectId, $emailedOtp->idempotencyKey,
        ]);
        self::assertSame(['code' => '042138', 'validityMinutes' => 10], $emailedOtp->parameters);

        self::assertInstanceOf(RequestNotification::class, $verification);
        self::assertSame(['PORTAL_EMAIL_VERIFICATION', 'EMAIL', 'new@example.com', null, 'otp_challenge', 'challenge-2', 'otp:challenge-2:1'], [
            $verification->purpose, $verification->channel, $verification->recipientAddress, $verification->clinicId, $verification->subjectType, $verification->subjectId, $verification->idempotencyKey,
        ]);
        self::assertSame(['code' => '042139', 'validityMinutes' => 10], $verification->parameters);

        self::assertInstanceOf(RequestNotification::class, $notice);
        self::assertSame(['PORTAL_EMAIL_CHANGED_NOTICE', 'EMAIL', 'old@example.com', null, 'user', 'user-3', 'email-changed:challenge-2'], [
            $notice->purpose, $notice->channel, $notice->recipientAddress, $notice->clinicId, $notice->subjectType, $notice->subjectId, $notice->idempotencyKey,
        ]);
        self::assertSame(['maskedNewEmail' => 'n***@example.com', 'changedAtLabel' => '01/11/2026 à 14:05'], $notice->parameters);
        self::assertSame(['user-3', 'user-3', null], [$verification->accountId, $notice->accountId, $otp->accountId], 'masked with their account, whatever address they went to');
    }
}
