<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Adapter\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Port\PasswordResetRequest;
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

        [$invitation, $reset] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $invitation);
        self::assertSame(['STAFF_INVITATION', 'EMAIL', 'hugo@example.com', 'Hugo', 'clinic-1', null, 'user', 'user-1', 'invitation:tok-1'], [
            $invitation->purpose, $invitation->channel, $invitation->recipientAddress, $invitation->recipientName, $invitation->clinicId, $invitation->clientId, $invitation->subjectType, $invitation->subjectId, $invitation->idempotencyKey,
        ]);
        self::assertSame(['firstName' => 'Hugo', 'inviterName' => 'Dr Claire Lambert', 'clinicName' => 'Clinique Montmartre', 'clinicCity' => 'Paris', 'roleLabel' => 'Vétérinaire', 'activationUrl' => 'https://x/invitation/s'], $invitation->parameters);

        self::assertInstanceOf(RequestNotification::class, $reset);
        self::assertSame(['PASSWORD_RESET', 'EMAIL', 'claire@example.com', null, 'user-2', 'password-reset:tok-2'], [$reset->purpose, $reset->channel, $reset->recipientAddress, $reset->clinicId, $reset->subjectId, $reset->idempotencyKey]);
        self::assertSame(['accountEmail' => 'claire@example.com', 'resetUrl' => 'https://x/reinitialiser/s', 'requestedAtLabel' => '26/10/2026 à 08:14'], $reset->parameters);
    }
}
