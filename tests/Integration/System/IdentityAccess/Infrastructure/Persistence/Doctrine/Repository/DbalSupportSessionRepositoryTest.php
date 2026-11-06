<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\SupportSessionEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalSupportSessionRepositoryTest extends KernelTestCase
{
    private const string FIRST     = '01960000-0000-7000-8000-00000000fb11';
    private const string SECOND    = '01960000-0000-7000-8000-00000000fb12';
    private const string WAITING   = '01960000-0000-7000-8000-00000000fb13';
    private const string ELSEWHERE = '01960000-0000-7000-8000-00000000fb14';
    private const string CLINIC    = '01960000-0000-7000-8000-00000000fb21';
    private const string OTHER     = '01960000-0000-7000-8000-00000000fb22';
    private const string USER      = '01960000-0000-7000-8000-00000000fb31';
    private const string ADMIN     = '01960000-0000-7000-8000-00000000fb41';

    public function testAStartedSessionIsReadBackWholly(): void
    {
        $sessions = $this->sessions();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $sessions->save($this->start(self::FIRST, self::CLINIC, 'h1', '2026-11-06 10:00:00', 'Europe/Paris'));

        self::assertNull($sessions->find(self::SECOND));

        $found = $sessions->find(self::FIRST);
        self::assertNotNull($found);
        self::assertSame(
            [self::FIRST, self::CLINIC, self::USER, self::ADMIN, 'Djamil Soidiki', 'Ticket 42', SupportMode::READ, '2026-11-06 09:00:00', '2026-11-06 09:30:00', 'UTC', hash('sha256', 'h1'), null, null, 0, 0],
            [
                $found->id(), $found->clinicId(), $found->userId(), $found->adminId(), $found->adminName(), $found->reason(), $found->mode(),
                $found->startedAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s'), $found->expiresAt()->getTimezone()->getName(),
                $found->handoffHash(), $found->claimedAt(), $found->endedAt(), $found->pages(), $found->writes(),
            ],
        );
    }

    public function testTheClaimTheCountsAndTheEndAreSavedAgain(): void
    {
        $sessions = $this->sessions();
        $sessions->save($this->start(self::FIRST, self::CLINIC, 'h1', '2026-11-06 09:00:00'));

        $session = $this->connection()->transactional(static fn (): ?SupportSession => $sessions->findByHandoffHashForUpdate(hash('sha256', 'h1')));
        self::assertNotNull($session);
        self::assertNull($sessions->findByHandoffHashForUpdate(hash('sha256', 'unknown')));

        $session->claim(new \DateTimeImmutable('2026-11-06 09:01:00', new \DateTimeZone('UTC')));
        $session->recordPage(false);
        $session->recordPage(true);
        $session->end(new \DateTimeImmutable('2026-11-06 10:20:00', new \DateTimeZone('Europe/Paris')));
        $sessions->save($session);

        $found = $sessions->find(self::FIRST);
        self::assertNotNull($found);
        self::assertSame(['2026-11-06 09:01:00', '2026-11-06 09:20:00', 'UTC', 2, 1, 19, 'Ticket 42', '2026-11-06 09:00:00'], [
            $found->claimedAt()?->format('Y-m-d H:i:s'), $found->endedAt()?->format('Y-m-d H:i:s'), $found->endedAt()?->getTimezone()->getName(),
            $found->pages(), $found->writes(), $found->minutes(), $found->reason(), $found->startedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testTheClaimedSessionsOfAClinicAreListedNewestFirst(): void
    {
        $sessions = $this->sessions();
        $sessions->save($this->start(self::FIRST, self::CLINIC, 'h1', '2026-11-06 09:00:00', claimed: true));
        $sessions->save($this->start(self::SECOND, self::CLINIC, 'h2', '2026-11-06 10:00:00', claimed: true));
        $sessions->save($this->start(self::WAITING, self::CLINIC, 'h3', '2026-11-06 11:00:00'));
        $sessions->save($this->start(self::ELSEWHERE, self::OTHER, 'h4', '2026-11-06 12:00:00', claimed: true));

        self::assertSame([self::SECOND, self::FIRST], array_map(static fn (SupportSession $session): string => $session->id(), $sessions->ofClinic(self::CLINIC, 10)), 'a hand-off never taken is left out');
        self::assertSame([self::SECOND], array_map(static fn (SupportSession $session): string => $session->id(), $sessions->ofClinic(self::CLINIC, 1)));
        self::assertSame([], $sessions->ofClinic('01960000-0000-7000-8000-00000000fb2f', 10));
    }

    public function testTheEntityDescribesTheTable(): void
    {
        $this->sessions()->save($this->start(self::FIRST, self::CLINIC, 'h1', '2026-11-06 09:00:00', claimed: true));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(SupportSessionEntity::class, Uuid::fromString(self::FIRST));

        self::assertInstanceOf(SupportSessionEntity::class, $entity);
        self::assertSame([self::CLINIC, 'read', 'Djamil Soidiki', hash('sha256', 'h1'), '09:00', null, 0], [
            $entity->clinicId->toRfc4122(), $entity->mode, $entity->adminName, $entity->handoffHash, $entity->claimedAt?->format('H:i'), $entity->endedAt, $entity->pages,
        ]);
    }

    private function start(string $id, string $clinicId, string $secret, string $at, string $zone = 'UTC', bool $claimed = false): SupportSession
    {
        $startedAt = new \DateTimeImmutable($at, new \DateTimeZone($zone));
        $session   = SupportSession::start($id, $clinicId, self::USER, self::ADMIN, 'Djamil Soidiki', 'Ticket 42', SupportMode::READ, 30, hash('sha256', $secret), $startedAt);

        if ($claimed) {
            $session->claim($startedAt);
        }

        return $session;
    }

    private function sessions(): SupportSessionRepositoryInterface
    {
        $repository = self::getContainer()->get(SupportSessionRepositoryInterface::class);
        \assert($repository instanceof SupportSessionRepositoryInterface);

        return $repository;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
