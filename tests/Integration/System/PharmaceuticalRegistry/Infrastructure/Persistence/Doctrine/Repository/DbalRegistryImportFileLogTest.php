<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFileLogInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\RegistryImportFileEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalRegistryImportFileLogTest extends KernelTestCase
{
    private const string OLD    = '01960000-0000-7000-8000-0000000fb001';
    private const string NEW    = '01960000-0000-7000-8000-0000000fb002';
    private const string FAILED = '01960000-0000-7000-8000-0000000fb003';

    public function testTheFilesOfAnImportAreRecordedThenReadBack(): void
    {
        $log = $this->log();
        // A Paris time: stored in UTC, read back in UTC.
        $log->record(self::OLD, 'ANMV', [
            new RegistryImportFile('amm', 'amm.xml', str_repeat('a', 64), 3_565_158, 'archive/2026-10-30/amm.xml', new \DateTimeImmutable('2026-10-30 08:00:00', new \DateTimeZone('Europe/Paris')), 'Djamil Soidiki'),
            new RegistryImportFile('dict', 'dict.xml', str_repeat('b', 64), 81_920, 'archive/2026-10-30/dict.xml', new \DateTimeImmutable('2026-10-30 08:00:00', new \DateTimeZone('Europe/Paris')), null),
        ]);

        $files = $log->filesOf([self::OLD, self::NEW]);

        self::assertSame([self::OLD], array_keys($files));
        self::assertSame(
            [['amm', 'amm.xml', str_repeat('a', 64), 3_565_158, 'archive/2026-10-30/amm.xml', '2026-10-30 07:00:00', 'Djamil Soidiki'], ['dict', 'dict.xml', str_repeat('b', 64), 81_920, 'archive/2026-10-30/dict.xml', '2026-10-30 07:00:00', null]],
            array_map(static fn (RegistryImportFile $f): array => [$f->kind, $f->name, $f->sha256, $f->size, $f->storedPath, $f->receivedAt->format('Y-m-d H:i:s'), $f->importedBy], $files[self::OLD]),
        );
        self::assertSame([], $log->filesOf([]));
    }

    public function testTheHashOfTheLastImportThatWentThrough(): void
    {
        $log = $this->log();
        self::assertNull($log->lastAppliedRegistryHash('ANMV'), 'no import recorded');

        $this->snapshot(self::OLD, 'APPLIED', '2026-10-30 06:00:00');
        $this->snapshot(self::NEW, 'APPLIED', '2026-11-06 06:00:00');
        $this->snapshot(self::FAILED, 'FAILED', '2026-11-06 07:00:00');
        foreach ([self::OLD => 'old', self::NEW => 'new', self::FAILED => 'failed'] as $id => $hash) {
            $log->record($id, 'ANMV', [new RegistryImportFile('amm', 'amm.xml', $hash, 1, 'archive/x/amm.xml', new \DateTimeImmutable(), null), new RegistryImportFile('dict', 'dict.xml', $hash . '-dict', 1, 'archive/x/dict.xml', new \DateTimeImmutable(), null)]);
        }

        self::assertSame('new', $log->lastAppliedRegistryHash('ANMV'), 'the newest applied one, not the failed one, not a dictionary');
        self::assertNull($log->lastAppliedRegistryHash('EMA_UPD'));
    }

    public function testTheEntityDescribesTheTable(): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($manager instanceof EntityManagerInterface);
        $at = new \DateTimeImmutable('2026-11-06 06:00:00');
        $manager->persist(new RegistryImportFileEntity(Uuid::v7()->toBinary(), Uuid::fromString(self::OLD)->toBinary(), 'ANMV', 'amm', 'amm.xml', str_repeat('c', 64), '12', 'archive/x/amm.xml', $at, null, $at));
        $manager->flush();

        self::assertSame(str_repeat('c', 64), $this->log()->filesOf([self::OLD])[self::OLD][0]->sha256 ?? null);
    }

    private function snapshot(string $id, string $status, string $at): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->insert('pharmaceutical_registry__snapshots', ['id' => Uuid::fromString($id)->toBinary(), 'source' => 'ANMV', 'status' => $status, 'downloaded_at' => $at]);
    }

    private function log(): RegistryImportFileLogInterface
    {
        $log = self::getContainer()->get(RegistryImportFileLogInterface::class);
        \assert($log instanceof RegistryImportFileLogInterface);

        return $log;
    }
}
