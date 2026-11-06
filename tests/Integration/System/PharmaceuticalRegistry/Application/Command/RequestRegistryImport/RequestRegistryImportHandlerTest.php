<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport;

use App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport\RequestRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Command\RequestRegistryImport\RequestRegistryImportHandler;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\PharmaceuticalRegistry\Support\InMemoryRegistryImportFileLog;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * « Lancer l'import » runs the real import cycle on the sample files of the
 * ANMV parser tests (ten products), then archives and records them.
 */
final class RequestRegistryImportHandlerTest extends KernelTestCase
{
    public function testTheWaitingFilesAreImportedArchivedAndRecorded(): void
    {
        $files          = new WaitingRegistryFiles('sample-hash');
        $log            = new InMemoryRegistryImportFileLog('an-older-hash');
        $clock          = new FrozenClock(new \DateTimeImmutable('2026-11-06 06:30:00', new \DateTimeZone('UTC')));
        $authorizations = $this->imports()->authorizationCount();

        self::assertTrue($this->handler($files, $log, $clock)(new RequestRegistryImport('ANMV', 'Djamil Soidiki')));

        self::assertSame([['ANMV', $clock->now()]], $files->archived);
        $latest = $this->imports()->imports('ANMV', 1)[0] ?? null;
        self::assertNotNull($latest);
        self::assertSame(['APPLIED', 10], [$latest->status, $latest->created]);
        self::assertSame($authorizations + 10, $this->imports()->authorizationCount());

        $recorded = $log->files[$latest->id] ?? [];
        self::assertSame(
            [['amm', 'sample-hash', 'archive/2026-11-06/amm.xml', 'Djamil Soidiki'], ['dict', 'sample-hash-dict', 'archive/2026-11-06/dict.xml', 'Djamil Soidiki']],
            array_map(static fn (RegistryImportFile $f): array => [$f->kind, $f->sha256, $f->storedPath, $f->importedBy], $recorded),
        );
        self::assertSame('ANMV', $log->sources[$latest->id] ?? null);
    }

    public function testTheVeryFilesOfTheLastImportAreNotImportedAgain(): void
    {
        $files  = new WaitingRegistryFiles('same-hash');
        $before = \count($this->imports()->imports('ANMV', 1000));

        self::assertFalse($this->handler($files, new InMemoryRegistryImportFileLog('same-hash'), new FrozenClock(new \DateTimeImmutable()))(new RequestRegistryImport()));

        self::assertSame([], $files->archived, 'they stay where they are');
        self::assertCount($before, $this->imports()->imports('ANMV', 1000), 'no import ran');
    }

    public function testNothingRunsWithoutFiles(): void
    {
        $files  = new WaitingRegistryFiles(null);
        $before = \count($this->imports()->imports('ANMV', 1000));

        self::assertFalse($this->handler($files, new InMemoryRegistryImportFileLog(), new FrozenClock(new \DateTimeImmutable()))(new RequestRegistryImport()));

        self::assertSame([], $files->archived);
        self::assertCount($before, $this->imports()->imports('ANMV', 1000), 'no import ran');
        self::assertNull($files->kept('ANMV', 'archive/x/amm.xml'));
    }

    private function handler(RegistryImportFilesInterface $files, InMemoryRegistryImportFileLog $log, FrozenClock $clock): RequestRegistryImportHandler
    {
        $cycle = self::getContainer()->get(RunFullImportCycleHandler::class);
        \assert($cycle instanceof RunFullImportCycleHandler);

        return new RequestRegistryImportHandler($files, $log, $cycle, $clock);
    }

    private function imports(): RegistryImportReadRepositoryInterface
    {
        $repository = self::getContainer()->get(RegistryImportReadRepositoryInterface::class);
        \assert($repository instanceof RegistryImportReadRepositoryInterface);

        return $repository;
    }
}
