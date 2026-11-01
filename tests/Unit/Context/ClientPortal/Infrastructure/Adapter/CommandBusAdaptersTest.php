<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Adapter;

use App\Context\ClientPortal\Application\Port\IdentityClaimRequest;
use App\Context\ClientPortal\Application\Port\IdentityClaimResult;
use App\Context\ClientPortal\Application\Port\PortalInvitationMessage;
use App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess\CommandBusPortalIdentity;
use App\Context\ClientPortal\Infrastructure\Adapter\Notification\CommandBusNotificationRequester;
use App\Context\ClientPortal\Infrastructure\Link\PortalInvitationLinkBuilder;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimOutcome;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimPortalIdentity;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimResult;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\PortalUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUser;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegistrationResult;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhone;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

final class CommandBusAdaptersTest extends TestCase
{
    public function testEachSendOfAnInvitationIsItsOwnMessage(): void
    {
        $bus       = self::bus(static fn (): string => 'message-id');
        $requester = new CommandBusNotificationRequester($bus);
        $sentAt    = new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC'));

        $requester->requestPortalInvitation(new PortalInvitationMessage('inv-1', 'clinic-1', 'client-1', 'sms', '+33612345678', 'Sophie Martin', 'Clinique des Lilas', 'Sophie', 'https://p/invitation#t=s', '05/11/2026', $sentAt));
        $requester->requestPortalInvitation(new PortalInvitationMessage('inv-1', 'clinic-1', 'client-1', 'email', 's@example.com', 'Sophie Martin', 'Clinique des Lilas', 'Sophie', 'https://p/invitation#t=t', '06/11/2026', $sentAt->modify('+1 day')));

        [$sms, $email] = $bus->commands;
        self::assertInstanceOf(RequestNotification::class, $sms);
        self::assertSame(['PORTAL_INVITATION', 'SMS', '+33612345678', 'Sophie Martin', 'clinic-1', 'client-1', 'portal_invitation', 'inv-1', 'portal-invitation:inv-1:1793264400'], [
            $sms->purpose, $sms->channel, $sms->recipientAddress, $sms->recipientName, $sms->clinicId, $sms->clientId, $sms->subjectType, $sms->subjectId, $sms->idempotencyKey,
        ]);
        self::assertSame(['clinicName' => 'Clinique des Lilas', 'ownerFirstName' => 'Sophie', 'link' => 'https://p/invitation#t=s', 'expiresOn' => '05/11/2026'], $sms->parameters);

        self::assertInstanceOf(RequestNotification::class, $email);
        self::assertSame(['EMAIL', 's@example.com', 'portal-invitation:inv-1:1793350800'], [$email->channel, $email->recipientAddress, $email->idempotencyKey]);
    }

    public function testTheClaimAndTheGrantRevocationGoToIdentityAccess(): void
    {
        $bus = self::bus(static fn (object $command): mixed => $command instanceof ClaimPortalIdentity
            ? new ClaimOutcome(ClaimResult::ALREADY_VERIFIED, 'user-1')
            : 2);
        $identity = new CommandBusPortalIdentity($bus);

        $claim = $identity->claimIdentity(new IdentityClaimRequest('inv-1', '+33612345678', 'ch-1', '123456', null, 's@example.com', false));
        $identity->revokeOnboardingGrants('+33612345678');

        self::assertSame([IdentityClaimResult::ALREADY_VERIFIED, 'user-1'], [$claim->result, $claim->userId]);
        [$command, $revoke] = $bus->commands;
        self::assertInstanceOf(ClaimPortalIdentity::class, $command);
        self::assertSame(['inv-1', '+33612345678', 'ch-1', '123456', null, 's@example.com', false], [
            $command->invitationRef, $command->expectedPhone, $command->challengeId, $command->code, $command->onboardingToken, $command->email, $command->createIfMissing,
        ]);
        self::assertInstanceOf(RevokeOnboardingGrantsForPhone::class, $revoke);
        self::assertSame('+33612345678', $revoke->phone);
    }

    public function testEveryClaimResultIsMapped(): void
    {
        foreach (ClaimResult::cases() as $result) {
            $identity = new CommandBusPortalIdentity(self::bus(static fn (): ClaimOutcome => new ClaimOutcome($result)));

            self::assertSame($result->value, $identity->claimIdentity(new IdentityClaimRequest('i', '+33612345678', null, null, 'g', null, true))->result->value);
        }
    }

    public function testARegistrationGoesToIdentityAccessAndEveryResultIsMapped(): void
    {
        foreach (RegistrationResult::cases() as $result) {
            $bus          = self::bus(static fn (): PortalUserRegistration => new PortalUserRegistration($result, 'user-1'));
            $registration = new CommandBusPortalIdentity($bus)->registerPortalUser('reg-token', 'o@example.com');

            self::assertSame([$result->value, 'user-1'], [$registration->result->value, $registration->userId]);
            $command = $bus->commands[0];
            self::assertInstanceOf(RegisterPortalUser::class, $command);
            self::assertSame(['reg-token', 'o@example.com'], [$command->registrationToken, $command->email]);
        }
    }

    public function testTheLinkCarriesTheTokenInItsFragment(): void
    {
        self::assertSame('https://portal.kiveto.app/invitation#t=a-b_c%2B', new PortalInvitationLinkBuilder('https://portal.kiveto.app/')->invitationLink('a-b_c+'));
    }

    /**
     * @param \Closure(object): mixed $answer
     */
    private static function bus(\Closure $answer): AnsweringCommandBus
    {
        return new AnsweringCommandBus($answer);
    }
}
