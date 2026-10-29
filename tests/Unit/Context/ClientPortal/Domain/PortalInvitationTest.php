<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Domain;

use App\Context\ClientPortal\Domain\Event\PortalInvitationAccepted;
use App\Context\ClientPortal\Domain\Event\PortalInvitationSent;
use App\Context\ClientPortal\Domain\Exception\ClientNotInvitableException;
use App\Context\ClientPortal\Domain\Exception\InvitationAlreadyUsedException;
use App\Context\ClientPortal\Domain\Exception\InvitationExpiredException;
use App\Context\ClientPortal\Domain\Invitation\InvitationChannel;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PortalInvitationTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-00000000f001';
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';
    private const string CLIENT = '01960000-0000-7000-8000-00000000d001';

    public function testASentInvitationIsPendingForSevenDays(): void
    {
        $now        = new \DateTimeImmutable('2026-10-29 09:00:00');
        $invitation = $this->send(InvitationChannel::EMAIL, 'sophie@example.com', $now);

        self::assertSame([self::ID, self::CLINIC, self::CLIENT, '+33612345678', 'sophie@example.com', InvitationChannel::EMAIL, 'hash-1', PortalInvitationStatus::PENDING], [
            $invitation->id()->toString(), $invitation->clinicId(), $invitation->clientId(), $invitation->phone()->toString(), $invitation->email(), $invitation->channel(), $invitation->tokenHash(), $invitation->status(),
        ]);
        self::assertSame(['2026-10-29 09:00', '2026-11-05 09:00'], [$invitation->sentAt()->format('Y-m-d H:i'), $invitation->expiresAt()->format('Y-m-d H:i')]);
        self::assertSame([null, null, null], [$invitation->acceptedAt(), $invitation->acceptedByUserId(), $invitation->revokedAt()]);

        $events = $invitation->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PortalInvitationSent::class, $events[0]);
        self::assertSame('client_portal.portal-invitation.sent.v1', $events[0]->name());
        self::assertSame(self::ID, $events[0]->aggregateId());
        self::assertSame(['invitationId' => self::ID, 'clinicId' => self::CLINIC, 'clientId' => self::CLIENT, 'channel' => 'email', 'reissued' => false, 'expiresAt' => $invitation->expiresAt()->format(\DATE_ATOM)], $events[0]->payload());
    }

    public function testTheEmailChannelRequiresAnEmail(): void
    {
        foreach ([null, '  '] as $email) {
            try {
                $this->send(InvitationChannel::EMAIL, $email, new \DateTimeImmutable());
                self::fail('An e-mail invitation needs an e-mail.');
            } catch (ClientNotInvitableException $e) {
                self::assertSame(ClientNotInvitableException::NO_EMAIL, $e->reason);
                self::assertSame('Client cannot be invited to the Portal: no_email.', $e->getMessage());
            }
        }

        self::assertNull($this->send(InvitationChannel::SMS, null, new \DateTimeImmutable())->email());
    }

    public function testExpiryIsDerivedFromAPendingInvitation(): void
    {
        $now        = new \DateTimeImmutable('2026-10-29 09:00:00');
        $invitation = $this->send(InvitationChannel::SMS, null, $now);

        self::assertFalse($invitation->isExpired($now->modify('+7 days -1 second')));
        self::assertTrue($invitation->isOpen($now->modify('+7 days -1 second')));
        self::assertTrue($invitation->isExpired($now->modify('+7 days')));
        self::assertFalse($invitation->isOpen($now->modify('+7 days')));

        $invitation->revoke($now->modify('+8 days'));
        self::assertFalse($invitation->isExpired($now->modify('+8 days')), 'a revoked invitation is not « expired »');
        self::assertFalse($invitation->isOpen($now));
    }

    public function testAReissueKeepsTheIdWithANewTokenExpiryAndAddresses(): void
    {
        $now        = new \DateTimeImmutable('2026-10-29 09:00:00');
        $invitation = $this->send(InvitationChannel::SMS, null, $now);
        (void) $invitation->pullDomainEvents();

        $later = $now->modify('+10 days');
        $invitation->reissue('hash-2', InvitationChannel::EMAIL, PhoneNumber::fromString('+33698765432'), 'new@example.com', $later);

        self::assertSame([self::ID, 'hash-2', InvitationChannel::EMAIL, '+33698765432', 'new@example.com', PortalInvitationStatus::PENDING], [
            $invitation->id()->toString(), $invitation->tokenHash(), $invitation->channel(), $invitation->phone()->toString(), $invitation->email(), $invitation->status(),
        ]);
        self::assertSame(['2026-11-08', '2026-11-15'], [$invitation->sentAt()->format('Y-m-d'), $invitation->expiresAt()->format('Y-m-d')]);
        self::assertFalse($invitation->isExpired($later));

        $events = $invitation->pullDomainEvents();
        self::assertInstanceOf(PortalInvitationSent::class, $events[0]);
        self::assertTrue($events[0]->reissued);

        $this->expectException(ClientNotInvitableException::class);
        $invitation->reissue('hash-3', InvitationChannel::EMAIL, PhoneNumber::fromString('+33698765432'), null, $later);
    }

    public function testOnlyAPendingInvitationIsReissued(): void
    {
        $invitation = $this->send(InvitationChannel::SMS, null, new \DateTimeImmutable('2026-10-29'));
        $invitation->accept('user-1', new \DateTimeImmutable('2026-10-30'));

        $this->expectException(InvitationAlreadyUsedException::class);
        $this->expectExceptionMessage('Invitation "' . self::ID . '" is no longer pending.');
        $invitation->reissue('hash-2', InvitationChannel::SMS, PhoneNumber::fromString('+33612345678'), null, new \DateTimeImmutable('2026-10-31'));
    }

    public function testAcceptanceRecordsTheAccount(): void
    {
        $invitation = $this->send(InvitationChannel::SMS, null, new \DateTimeImmutable('2026-10-29 09:00'));
        (void) $invitation->pullDomainEvents();
        $invitation->accept('user-1', new \DateTimeImmutable('2026-10-30 10:00'));

        self::assertSame([PortalInvitationStatus::ACCEPTED, '2026-10-30 10:00', 'user-1'], [$invitation->status(), $invitation->acceptedAt()?->format('Y-m-d H:i'), $invitation->acceptedByUserId()]);

        $events = $invitation->pullDomainEvents();
        self::assertInstanceOf(PortalInvitationAccepted::class, $events[0]);
        self::assertSame(self::ID, $events[0]->aggregateId());
        self::assertSame(['invitationId' => self::ID, 'clinicId' => self::CLINIC, 'clientId' => self::CLIENT, 'userId' => 'user-1'], $events[0]->payload());
    }

    public function testAnExpiredInvitationCannotBeAccepted(): void
    {
        $invitation = $this->send(InvitationChannel::SMS, null, new \DateTimeImmutable('2026-10-29 09:00'));

        $this->expectException(InvitationExpiredException::class);
        $this->expectExceptionMessage('Invitation "' . self::ID . '" has expired.');
        $invitation->accept('user-1', new \DateTimeImmutable('2026-11-05 09:00'));
    }

    public function testAnAcceptedInvitationCannotBeAcceptedAgainNorRevoked(): void
    {
        $invitation = $this->send(InvitationChannel::SMS, null, new \DateTimeImmutable('2026-10-29 09:00'));
        $invitation->accept('user-1', new \DateTimeImmutable('2026-10-29 10:00'));

        try {
            $invitation->accept('user-2', new \DateTimeImmutable('2026-10-29 11:00'));
            self::fail('Accepted twice.');
        } catch (InvitationAlreadyUsedException) {
        }

        $this->expectException(InvitationAlreadyUsedException::class);
        $invitation->revoke(new \DateTimeImmutable('2026-10-29 11:00'));
    }

    public function testRevocationClosesThePendingInvitation(): void
    {
        $invitation = $this->send(InvitationChannel::SMS, null, new \DateTimeImmutable('2026-10-29 09:00'));
        $invitation->revoke(new \DateTimeImmutable('2026-11-29 09:00'));

        self::assertSame([PortalInvitationStatus::REVOKED, '2026-11-29'], [$invitation->status(), $invitation->revokedAt()?->format('Y-m-d')]);
    }

    public function testReconstitutionRestoresStateWithoutEvents(): void
    {
        $invitation = PortalInvitation::reconstitute(
            PortalInvitationId::fromString(self::ID),
            self::CLINIC,
            self::CLIENT,
            PhoneNumber::fromString('+33612345678'),
            null,
            InvitationChannel::SMS,
            'h',
            PortalInvitationStatus::ACCEPTED,
            new \DateTimeImmutable('2026-10-01'),
            new \DateTimeImmutable('2026-10-08'),
            new \DateTimeImmutable('2026-10-02'),
            'user-1',
            null,
        );

        self::assertSame([PortalInvitationStatus::ACCEPTED, 'user-1', '2026-10-02'], [$invitation->status(), $invitation->acceptedByUserId(), $invitation->acceptedAt()?->format('Y-m-d')]);
        self::assertSame([], $invitation->recordedDomainEvents());
    }

    private function send(InvitationChannel $channel, ?string $email, \DateTimeImmutable $now): PortalInvitation
    {
        return PortalInvitation::send(PortalInvitationId::fromString(self::ID), self::CLINIC, self::CLIENT, PhoneNumber::fromString('+33612345678'), $email, $channel, 'hash-1', $now);
    }
}
