<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Export\DataExport;
use App\Context\ClientPortal\Domain\Export\DataExportId;
use App\Context\ClientPortal\Domain\Export\DataExportRepositoryInterface;
use App\Context\ClientPortal\Domain\Export\DataExportStatus;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\DataExportEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalDataExportRepositoryTest extends KernelTestCase
{
    private const string E1    = '01960000-0000-7000-8000-0000000ee001';
    private const string E2    = '01960000-0000-7000-8000-0000000ee002';
    private const string E3    = '01960000-0000-7000-8000-0000000ee003';
    private const string E4    = '01960000-0000-7000-8000-0000000ee004';
    private const string USER  = '01960000-0000-7000-8000-0000000ee101';
    private const string USER2 = '01960000-0000-7000-8000-0000000ee102';

    public function testAnExportIsReadBackAndMovesThroughItsStates(): void
    {
        $exports = $this->exports();
        $export  = DataExport::request(DataExportId::fromString(self::E1), self::USER, new \DateTimeImmutable('2026-11-02 11:00', new \DateTimeZone('Europe/Paris')));
        $exports->save($export);

        $found = $exports->findById(self::E1);
        self::assertNotNull($found);
        self::assertSame([self::E1, self::USER, DataExportStatus::PENDING, '2026-11-02 10:00', 'UTC', null, null, null], [
            $found->id()->toString(), $found->userId(), $found->status(), $found->requestedAt()->format('Y-m-d H:i'), $found->requestedAt()->getTimezone()->getName(), $found->readyAt(), $found->expiresAt(), $found->failedAt(),
        ]);

        $found->markReady(new \DateTimeImmutable('2026-11-02 10:05', new \DateTimeZone('UTC')));
        $exports->save($found);
        $ready = $exports->findById(self::E1);
        self::assertSame([DataExportStatus::READY, '2026-11-02 10:05', '2026-11-04 10:05', null], [$ready?->status(), $ready?->readyAt()?->format('Y-m-d H:i'), $ready?->expiresAt()?->format('Y-m-d H:i'), $ready?->failedAt()]);

        $failed = DataExport::request(DataExportId::fromString(self::E2), self::USER, new \DateTimeImmutable('2026-11-02 12:00', new \DateTimeZone('UTC')));
        $failed->markFailed(new \DateTimeImmutable('2026-11-02 12:01', new \DateTimeZone('UTC')));
        $exports->save($failed);
        self::assertSame([DataExportStatus::FAILED, '2026-11-02 12:01'], [$exports->findById(self::E2)?->status(), $exports->findById(self::E2)?->failedAt()?->format('Y-m-d H:i')]);

        self::assertNull($exports->findById(self::E3));
        self::assertNull($exports->findById('not-a-uuid'));
    }

    public function testTheExportsOfAUserTheExpiredOnesAndTheRemoval(): void
    {
        $exports = $this->exports();
        $this->ready(self::E1, self::USER, '2026-10-30 08:00');
        $this->ready(self::E2, self::USER, '2026-10-31 08:00');
        $this->ready(self::E3, self::USER2, '2026-10-29 08:00');
        $exports->save(DataExport::request(DataExportId::fromString(self::E4), self::USER, new \DateTimeImmutable('2026-11-02 08:00', new \DateTimeZone('UTC'))));

        $ids = static fn (array $list): array => array_map(static fn (mixed $e): string => $e instanceof DataExport ? $e->id()->toString() : '', $list);
        self::assertSame([self::E4, self::E2, self::E1], $ids($exports->findByUser(self::USER)));

        $now = new \DateTimeImmutable('2026-10-30 08:00', new \DateTimeZone('UTC'));
        self::assertSame([self::E3, self::E1], $ids($exports->findReadyExpiredAt($now, 10)));
        self::assertSame([self::E3], $ids($exports->findReadyExpiredAt($now, 1)));

        $first = $exports->findById(self::E1);
        self::assertNotNull($first);
        $exports->remove($first);
        self::assertSame([self::E4, self::E2], $ids($exports->findByUser(self::USER)));
    }

    public function testTheTableIsMappedForTheSchemaTools(): void
    {
        $this->ready(self::E1, self::USER, '2026-10-30 08:00');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(DataExportEntity::class, Uuid::fromString(self::E1));
        self::assertInstanceOf(DataExportEntity::class, $entity);
        self::assertSame(['ready', '2026-10-30 08:00', null], [$entity->status, $entity->expiresAt?->format('Y-m-d H:i'), $entity->failedAt]);

        $built = new DataExportEntity(Uuid::v7(), Uuid::v7(), 'pending', new \DateTimeImmutable(), null, null, null);
        self::assertSame('pending', $built->status);
    }

    /** A ready export whose 48 hours end at the given instant (UTC). */
    private function ready(string $id, string $userId, string $expiresAt): void
    {
        $at     = new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'));
        $export = DataExport::request(DataExportId::fromString($id), $userId, $at);
        $export->markReady($at->modify('-48 hours'));
        $this->exports()->save($export);
    }

    private function exports(): DataExportRepositoryInterface
    {
        $repository = self::getContainer()->get(DataExportRepositoryInterface::class);
        \assert($repository instanceof DataExportRepositoryInterface);

        return $repository;
    }
}
