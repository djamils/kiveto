<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Invitation\InvitationChannel;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PortalInvitationEntity;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalPortalInvitationRepositoryTest extends KernelTestCase
{
    private const string A       = '01960000-0000-7000-8000-0000000c0a01';
    private const string B       = '01960000-0000-7000-8000-0000000c0a02';
    private const string C       = '01960000-0000-7000-8000-0000000c0a03';
    private const string D       = '01960000-0000-7000-8000-0000000c0a04';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000c0c01';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000c0c02';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000c0d01';
    private const string USER    = '01960000-0000-7000-8000-0000000c0e01';
    private const string PHONE   = '+33612340901';

    public function testEveryColumnIsReadBackAndUpdatedInPlace(): void
    {
        $invitations = $this->invitations();
        // A Paris time: dates are stored in UTC and read back as UTC.
        $sentAt = new \DateTimeImmutable('2026-10-29 10:15:00', new \DateTimeZone('Europe/Paris'));
        $invitations->save($this->send(self::A, self::CLINIC, self::PHONE, 'h-a', $sentAt, 'sophie@example.com', InvitationChannel::EMAIL));

        $found = $invitations->findByTokenHash('h-a');
        self::assertNotNull($found);
        self::assertSame([self::A, self::CLINIC, self::CLIENT, self::PHONE, 'sophie@example.com', InvitationChannel::EMAIL, 'h-a', PortalInvitationStatus::PENDING], [
            $found->id()->toString(), $found->clinicId(), $found->clientId(), $found->phone()->toString(), $found->email(), $found->channel(), $found->tokenHash(), $found->status(),
        ]);
        self::assertSame(['2026-10-29 09:15:00', '2026-11-05 09:15:00', 'UTC', null, null, null], [
            $found->sentAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s'), $found->sentAt()->getTimezone()->getName(), $found->acceptedAt(), $found->acceptedByUserId(), $found->revokedAt(),
        ]);

        $this->em()->wrapInTransaction(static function () use ($invitations, $sentAt): void {
            $locked = $invitations->findByTokenHashForUpdate('h-a');
            self::assertNotNull($locked);
            $locked->reissue('h-a2', InvitationChannel::SMS, PhoneNumber::fromString('+33612340902'), null, $sentAt->modify('+1 day'));
            $locked->accept(self::USER, $sentAt->modify('+2 days'));
            $invitations->save($locked);
        });

        self::assertNull($invitations->findByTokenHash('h-a'), 'the old token is dead');
        $accepted = $this->em()->wrapInTransaction(static fn (): ?PortalInvitation => $invitations->findByIdForUpdate(PortalInvitationId::fromString(self::A)));
        self::assertNotNull($accepted);
        self::assertSame(['h-a2', InvitationChannel::SMS, '+33612340902', null, PortalInvitationStatus::ACCEPTED, '2026-10-30 09:15', '2026-10-31 09:15', self::USER], [
            $accepted->tokenHash(), $accepted->channel(), $accepted->phone()->toString(), $accepted->email(), $accepted->status(), $accepted->sentAt()->format('Y-m-d H:i'), $accepted->acceptedAt()?->format('Y-m-d H:i'), $accepted->acceptedByUserId(),
        ]);

        $accepted = PortalInvitation::reconstitute($accepted->id(), self::CLINIC, self::CLIENT, $accepted->phone(), null, InvitationChannel::SMS, 'h-a2', PortalInvitationStatus::REVOKED, $accepted->sentAt(), $accepted->expiresAt(), null, null, $sentAt->modify('+3 days'));
        $invitations->save($accepted);
        self::assertSame('2026-11-01 09:15', $invitations->findByTokenHashForUpdate('h-a2')?->revokedAt()?->format('Y-m-d H:i'));
        self::assertNull($invitations->findByIdForUpdate(PortalInvitationId::fromString(self::B)));
        self::assertNull($invitations->findByTokenHashForUpdate('unknown'));
    }

    public function testThePendingInvitationsOfAPhoneExcludeExpiredRevokedAndOtherPhones(): void
    {
        $invitations = $this->invitations();
        $now         = new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC'));
        $invitations->save($this->send(self::A, self::CLINIC, self::PHONE, 'h-a', $now->modify('-2 days')));
        $invitations->save($this->send(self::B, self::CLINIC2, self::PHONE, 'h-b', $now->modify('-1 day')));
        $invitations->save($this->send(self::C, self::CLINIC, self::PHONE, 'h-c', $now->modify('-8 days'))); // expired
        $revoked = $this->send(self::D, self::CLINIC2, '+33612340902', 'h-d', $now);
        $invitations->save($revoked);

        $ids = static fn (mixed $list): array => \is_array($list) ? array_map(static fn (mixed $i): string => $i instanceof PortalInvitation ? $i->id()->toString() : '', $list) : [];

        self::assertSame([self::A, self::B], $ids($invitations->findPendingByPhone(PhoneNumber::fromString(self::PHONE), $now)));
        self::assertSame([self::B], $ids($invitations->findPendingByPhone(PhoneNumber::fromString(self::PHONE), $now->modify('+5 days'))));

        $revoked->revoke($now);
        $invitations->save($revoked);
        self::assertSame([], $invitations->findPendingByPhone(PhoneNumber::fromString('+33612340902'), $now));
    }

    public function testTheInvitationsOfAClientNewestFirst(): void
    {
        $invitations = $this->invitations();
        $now         = new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC'));
        $invitations->save($this->send(self::A, self::CLINIC, self::PHONE, 'h-a', $now->modify('-10 days'))); // expired, still pending
        $accepted = $this->send(self::B, self::CLINIC, self::PHONE, 'h-b', $now->modify('-20 days'));
        $accepted->accept(self::USER, $now->modify('-19 days'));
        $invitations->save($accepted);
        $invitations->save($this->send(self::C, self::CLINIC2, self::PHONE, 'h-c', $now));

        $pending = $this->em()->wrapInTransaction(static fn (): array => $invitations->findPendingForClientForUpdate(self::CLINIC, self::CLIENT));
        self::assertSame([self::A], array_map(static fn (PortalInvitation $i): string => $i->id()->toString(), $pending));
        self::assertSame(self::A, $invitations->findLatestForClient(self::CLINIC, self::CLIENT)?->id()->toString());
        self::assertSame(self::C, $invitations->findLatestForClient(self::CLINIC2, self::CLIENT)?->id()->toString());
        self::assertNull($invitations->findLatestForClient(self::CLINIC, self::USER));
    }

    public function testTheTableIsMappedForTheSchemaTools(): void
    {
        $this->invitations()->save($this->send(self::A, self::CLINIC, self::PHONE, 'h-a', new \DateTimeImmutable()));

        $entity = $this->em()->find(PortalInvitationEntity::class, Uuid::fromString(self::A));
        self::assertInstanceOf(PortalInvitationEntity::class, $entity);
        self::assertSame(['h-a', 'pending', self::PHONE], [$entity->tokenHash, $entity->status, $entity->phone]);

        $built = new PortalInvitationEntity(Uuid::v7(), Uuid::v7(), Uuid::v7(), self::PHONE, null, 'sms', 'h', 'pending', new \DateTimeImmutable(), new \DateTimeImmutable(), null, null, null);
        self::assertSame('sms', $built->channel);
    }

    private function send(string $id, string $clinicId, string $phone, string $hash, \DateTimeImmutable $at, ?string $email = null, InvitationChannel $channel = InvitationChannel::SMS): PortalInvitation
    {
        return PortalInvitation::send(PortalInvitationId::fromString($id), $clinicId, self::CLIENT, PhoneNumber::fromString($phone), $email, $channel, $hash, $at);
    }

    private function invitations(): PortalInvitationRepositoryInterface
    {
        $repository = self::getContainer()->get(PortalInvitationRepositoryInterface::class);
        \assert($repository instanceof PortalInvitationRepositoryInterface);

        return $repository;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
