<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\ClientLinkEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalClientLinkRepositoryTest extends KernelTestCase
{
    private const string L1      = '01960000-0000-7000-8000-0000000c1a01';
    private const string L2      = '01960000-0000-7000-8000-0000000c1a02';
    private const string L3      = '01960000-0000-7000-8000-0000000c1a03';
    private const string USER    = '01960000-0000-7000-8000-0000000c1e01';
    private const string USER2   = '01960000-0000-7000-8000-0000000c1e02';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000c1c01';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000c1c02';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000c1d01';

    public function testALinkIsReadBackRevokedAndReactivatedInPlace(): void
    {
        $links = $this->links();
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L1), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-10-29 10:00', new \DateTimeZone('Europe/Paris'))));

        $found = $this->em()->wrapInTransaction(static fn (): ?ClientLink => $links->findForUpdate(self::USER, self::CLINIC, self::CLIENT));
        self::assertNotNull($found);
        self::assertSame([self::L1, self::USER, self::CLINIC, self::CLIENT, ClientLinkStatus::CONFIRMED, '2026-10-29 09:00', 'UTC', null], [
            $found->id()->toString(), $found->userId(), $found->clinicId(), $found->clientId(), $found->status(), $found->linkedAt()?->format('Y-m-d H:i'), $found->linkedAt()?->getTimezone()->getName(), $found->revokedAt(),
        ]);
        self::assertTrue($links->hasConfirmed(self::USER, self::CLINIC));

        $found->revoke(new \DateTimeImmutable('2026-11-01 08:00', new \DateTimeZone('UTC')));
        $links->save($found);
        self::assertFalse($links->hasConfirmed(self::USER, self::CLINIC));
        self::assertSame([ClientLinkStatus::REVOKED, '2026-11-01 08:00'], [$links->findForUpdate(self::USER, self::CLINIC, self::CLIENT)?->status(), $links->findForUpdate(self::USER, self::CLINIC, self::CLIENT)?->revokedAt()?->format('Y-m-d H:i')]);

        $found->reactivate(new \DateTimeImmutable('2026-11-05 08:00', new \DateTimeZone('UTC')));
        $links->save($found);
        $again = $links->findForUpdate(self::USER, self::CLINIC, self::CLIENT);
        self::assertSame([ClientLinkStatus::CONFIRMED, '2026-11-05 08:00', null], [$again?->status(), $again?->linkedAt()?->format('Y-m-d H:i'), $again?->revokedAt()]);
        self::assertNull($links->findForUpdate(self::USER2, self::CLINIC, self::CLIENT));
    }

    public function testTheLinksOfAClientAndTheConfirmedLinksOfAUser(): void
    {
        $links = $this->links();
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L1), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-10-29 10:00')));
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L2), self::USER, self::CLINIC2, self::CLIENT, new \DateTimeImmutable('2026-10-20 10:00')));
        $links->save(ClientLink::reconstitute(ClientLinkId::fromString(self::L3), self::USER2, self::CLINIC, self::CLIENT, ClientLinkStatus::PENDING, null, null));

        $ids = static fn (array $list): array => array_map(static fn (mixed $l): string => $l instanceof ClientLink ? $l->id()->toString() : '', $list);
        self::assertSame([self::L1, self::L3], $ids($links->findByClient(self::CLINIC, self::CLIENT)));
        self::assertSame([self::L2, self::L1], $ids($links->findConfirmedByUser(self::USER)));
        self::assertSame([self::L1, self::L2], $ids($links->findByUser(self::USER)));
        self::assertSame([self::L3], $ids($links->findByUser(self::USER2)));
        self::assertSame([], $links->findConfirmedByUser(self::USER2), 'pending');
        self::assertFalse($links->hasConfirmed(self::USER2, self::CLINIC));
        self::assertNull($links->findByClient(self::CLINIC, self::CLIENT)[1]->linkedAt());
    }

    public function testPendingLinksByAccountAndByClinic(): void
    {
        $links = $this->links();
        $links->save(ClientLink::requestFromSuggestion(ClientLinkId::fromString(self::L2), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-11-02 11:00', new \DateTimeZone('Europe/Paris'))));
        $links->save(ClientLink::requestFromSuggestion(ClientLinkId::fromString(self::L3), self::USER2, self::CLINIC, self::CLIENT, new \DateTimeImmutable('2026-11-01 08:00', new \DateTimeZone('UTC'))));
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L1), self::USER, self::CLINIC2, self::CLIENT, new \DateTimeImmutable('2026-10-29 10:00')));
        $rejected = ClientLink::requestFromSuggestion(ClientLinkId::fromString('01960000-0000-7000-8000-0000000c1a04'), self::USER, self::CLINIC2, '01960000-0000-7000-8000-0000000c1d02', new \DateTimeImmutable('2026-11-01'));
        $rejected->reject(new \DateTimeImmutable('2026-11-02'));
        $links->save($rejected);

        $ids = static fn (array $list): array => array_map(static fn (mixed $l): string => $l instanceof ClientLink ? $l->id()->toString() : '', $list);
        self::assertSame([self::L3, self::L2], $ids($links->findPendingByClinic(self::CLINIC)), 'oldest request first');
        self::assertSame([], $links->findPendingByClinic(self::CLINIC2));
        self::assertSame([self::L2, self::L1], $ids($links->findActiveByUser(self::USER)), 'pending and confirmed, by clinic');

        $pending = $links->findPendingByClinic(self::CLINIC)[1];
        self::assertSame([ClientLinkStatus::PENDING, null, '2026-11-02 10:00', 'UTC'], [$pending->status(), $pending->linkedAt(), $pending->requestedAt()?->format('Y-m-d H:i'), $pending->requestedAt()?->getTimezone()->getName()]);
    }

    public function testOneLinkPerUserAndClinicClient(): void
    {
        $links = $this->links();
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L1), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable()));

        // A second link for the same triple lands on the existing row (unique key).
        $links->save(ClientLink::confirm(ClientLinkId::fromString(self::L2), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable()));

        self::assertSame([self::L1], array_map(static fn (ClientLink $l): string => $l->id()->toString(), $links->findByClient(self::CLINIC, self::CLIENT)));
    }

    public function testTheTableIsMappedForTheSchemaTools(): void
    {
        $this->links()->save(ClientLink::confirm(ClientLinkId::fromString(self::L1), self::USER, self::CLINIC, self::CLIENT, new \DateTimeImmutable()));

        $entity = $this->em()->find(ClientLinkEntity::class, Uuid::fromString(self::L1));
        self::assertInstanceOf(ClientLinkEntity::class, $entity);
        self::assertSame('confirmed', $entity->status);

        self::assertSame('pending', new ClientLinkEntity(Uuid::v7(), Uuid::v7(), Uuid::v7(), Uuid::v7(), 'pending', null, null)->status);
    }

    private function links(): ClientLinkRepositoryInterface
    {
        $repository = self::getContainer()->get(ClientLinkRepositoryInterface::class);
        \assert($repository instanceof ClientLinkRepositoryInterface);

        return $repository;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
