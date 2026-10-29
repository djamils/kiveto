<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command;

use App\Context\ClientPortal\Application\Command\SendPortalInvitation\SendPortalInvitation;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Domain\Exception\ClientNotInvitableException;
use App\Context\ClientPortal\Domain\Invitation\InvitationChannel;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SendPortalInvitationHandlerTest extends TestCase
{
    public function testAFirstInvitationIsCreatedSavedThenRequested(): void
    {
        $world                            = new W();
        $world->fakes->clinics[W::CLINIC] = new ClinicLabel('Clinique des Lilas', 'Lyon');
        $world->clock->now                = new \DateTimeImmutable('2026-10-29 23:30:00', new \DateTimeZone('UTC'));

        $id = $world->sender()(new SendPortalInvitation(W::CLINIC, W::CLIENT, 'sms', W::ACTOR));

        $invitation = $world->invitation($id);
        self::assertSame([W::CLINIC, W::CLIENT, W::PHONE, 'sophie@example.com', InvitationChannel::SMS, hash('sha256', 'secret-1'), PortalInvitationStatus::PENDING], [
            $invitation->clinicId(), $invitation->clientId(), $invitation->phone()->toString(), $invitation->email(), $invitation->channel(), $invitation->tokenHash(), $invitation->status(),
        ]);
        self::assertSame(['client'], $world->invitations->locks);
        self::assertSame(['client_portal.portal-invitation.sent.v1'], $world->fakes->eventNames());

        [$message] = $world->fakes->messages;
        self::assertSame([$id, W::CLINIC, W::CLIENT, 'sms', W::PHONE, 'Sophie Martin', 'Clinique des Lilas', 'Sophie', 'https://portal.test/invitation#t=secret-1'], [
            $message->invitationId, $message->clinicId, $message->clientId, $message->channel, $message->recipientAddress, $message->recipientName, $message->clinicName, $message->ownerFirstName, $message->link,
        ]);
        // 7 days after 23:30 UTC is already the next day in Paris.
        self::assertSame(['06/11/2026', '2026-10-29 23:30'], [$message->expiresOn, $message->sentAt->format('Y-m-d H:i')]);
    }

    public function testTheEmailChannelSendsTheLinkToTheEmail(): void
    {
        $world = new W();

        $world->sender()(new SendPortalInvitation(W::CLINIC, W::CLIENT, 'email', W::ACTOR));

        self::assertSame(['email', 'sophie@example.com'], [$world->fakes->messages[0]->channel, $world->fakes->messages[0]->recipientAddress]);
    }

    public function testAPendingOrExpiredInvitationIsReissuedUnderTheSameId(): void
    {
        $world      = new W();
        [$old, $id] = $world->invite();
        $world->clock->advance('+8 days');
        self::assertTrue($world->invitation($id)->isExpired($world->clock->now()));

        $again = $world->sender()(new SendPortalInvitation(W::CLINIC, W::CLIENT, 'email', W::ACTOR));

        self::assertSame($id, $again);
        self::assertCount(1, $world->invitations->invitations);
        $invitation = $world->invitation($id);
        self::assertSame([hash('sha256', 'secret-2'), InvitationChannel::EMAIL, '2026-11-13'], [$invitation->tokenHash(), $invitation->channel(), $invitation->expiresAt()->format('Y-m-d')]);
        self::assertNotSame(hash('sha256', $old), $invitation->tokenHash());
        self::assertTrue($world->fakes->events[0]->payload()['reissued']);
        self::assertSame('https://portal.test/invitation#t=secret-2', $world->fakes->messages[0]->link);
    }

    public function testAnAcceptedInvitationIsNotReissuedButANewOneIsCreated(): void
    {
        $world     = new W();
        [, $first] = $world->invite();
        $world->invitation($first)->accept('user-1', $world->clock->now());

        $second = $world->sender()(new SendPortalInvitation(W::CLINIC, W::CLIENT, 'sms', W::ACTOR));

        self::assertNotSame($first, $second);
        self::assertCount(2, $world->invitations->invitations);
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: ?string, 3: bool, 4: bool, 5: string}>
     */
    public static function provideAClientThatCannotBeInvitedIsRefusedAndNothingIsSentCases(): iterable
    {
        yield 'archived' => [W::CLIENT, W::PHONE, 'a@b.fr', true, true, ClientNotInvitableException::ARCHIVED];
        yield 'no mobile' => [W::CLIENT, null, 'a@b.fr', false, true, ClientNotInvitableException::NO_MOBILE];
        yield 'foreign mobile' => [W::CLIENT, '+447700900123', 'a@b.fr', false, false, ClientNotInvitableException::MOBILE_NOT_SUPPORTED];
        yield 'no email' => [W::CLIENT, W::PHONE, null, false, true, ClientNotInvitableException::NO_EMAIL];
        yield 'unknown client' => [W::CLIENT2, W::PHONE, 'a@b.fr', false, true, ClientNotInvitableException::UNKNOWN_CLIENT];
    }

    #[DataProvider('provideAClientThatCannotBeInvitedIsRefusedAndNothingIsSentCases')]
    public function testAClientThatCannotBeInvitedIsRefusedAndNothingIsSent(string $clientId, ?string $mobile, ?string $email, bool $archived, bool $supported, string $reason): void
    {
        $world = new W();
        $world->fakes->withClient(W::CLINIC, W::CLIENT, $mobile, $email, $archived);
        $world->fakes->mobile = $supported;

        try {
            $world->sender()(new SendPortalInvitation(W::CLINIC, $clientId, 'email', W::ACTOR));
            self::fail('Refused');
        } catch (ClientNotInvitableException $e) {
            self::assertSame($reason, $e->reason);
        }

        self::assertSame([[], [], 0], [$world->fakes->messages, $world->fakes->events, $world->invitations->saves]);
    }

    public function testAnUnknownChannelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown invitation channel "push".');

        new W()->sender()(new SendPortalInvitation(W::CLINIC, W::CLIENT, 'push', W::ACTOR));
    }
}
