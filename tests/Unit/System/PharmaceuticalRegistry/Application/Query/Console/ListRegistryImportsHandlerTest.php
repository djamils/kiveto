<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImports;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImportsHandler;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryStatusView;
use App\Tests\Unit\System\PharmaceuticalRegistry\Support\InMemoryRegistryImportFileLog;
use PHPUnit\Framework\TestCase;

final class ListRegistryImportsHandlerTest extends TestCase
{
    public function testTheRegistryShowsItsLatestImportsTheirFilesAndTheFilesWaiting(): void
    {
        $imports    = [self::import('a', 'RUNNING'), self::import('b', 'APPLIED'), self::import('c', 'APPLIED')];
        $modifiedAt = new \DateTimeImmutable('2026-11-06 06:00:00', new \DateTimeZone('UTC'));
        $files      = $this->createMock(RegistryImportFilesInterface::class);
        $files->expects(self::once())->method('current')->with('ANMV')->willReturn(self::waiting('new-hash', $modifiedAt));
        $log = new InMemoryRegistryImportFileLog('old-hash');
        $log->record('b', 'ANMV', [self::file('old-hash', 'Djamil Soidiki')]);

        $status = new ListRegistryImportsHandler($this->repository('ANMV', 8, $imports, 4210), $files, $log)(new ListRegistryImports());

        self::assertSame([4210, true, $modifiedAt, 'new-hash', false], [$status->authorizations, $status->filesReady, $status->filesModifiedAt, $status->filesHash, $status->sameAsLast]);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (RegistryImportView $i): string => $i->id, $status->imports));
        $latest    = $status->latest();
        $inService = $status->inService();
        self::assertNotNull($latest);
        self::assertNotNull($inService);
        self::assertSame(['a', 'b'], [$latest->id, $inService->id], 'in service: the latest import that went through');
        self::assertSame(['old-hash', 'Djamil Soidiki'], [$inService->registryFile()?->sha256, $inService->importedBy()]);
        self::assertNull($latest->importedBy(), 'no file kept for it');
    }

    public function testTheFilesOfTheLastImportAreNotImportedAgain(): void
    {
        $files = self::createStub(RegistryImportFilesInterface::class);
        $files->method('current')->willReturn(self::waiting('same-hash', new \DateTimeImmutable()));

        $status = new ListRegistryImportsHandler($this->repository('ANMV', 8, [], 0), $files, new InMemoryRegistryImportFileLog('same-hash'))(new ListRegistryImports());

        self::assertTrue($status->sameAsLast);
    }

    public function testNoFileWaitingAndAtLeastOneImportAsked(): void
    {
        $files = $this->createMock(RegistryImportFilesInterface::class);
        $files->expects(self::once())->method('current')->with('EMA_UPD')->willReturn(null);

        $status = new ListRegistryImportsHandler($this->repository('EMA_UPD', 1, [], 0), $files, new InMemoryRegistryImportFileLog('any'))(new ListRegistryImports(0, 'EMA_UPD'));

        self::assertSame([false, null, null, false, null, null], [$status->filesReady, $status->filesModifiedAt, $status->filesHash, $status->sameAsLast, $status->latest(), $status->inService()]);
    }

    public function testNoImportWentThroughYet(): void
    {
        $status = new RegistryStatusView(0, [self::import('a', 'FAILED'), self::import('b', 'RUNNING')], false, null);
        $latest = $status->latest();

        self::assertNotNull($latest);
        self::assertSame('a', $latest->id);
        self::assertNull($status->inService());
        self::assertNull($latest->registryFile());
    }

    public function testTheSizeOfAFile(): void
    {
        self::assertSame(['512 o', '812 Ko', '3,4 Mo'], array_map(
            static fn (int $size): string => new RegistryImportFile('amm', 'amm.xml', 'h', $size, 'archive/x/amm.xml', new \DateTimeImmutable(), null)->sizeLabel(),
            [512, 812 * 1024 + 20, (int) (3.4 * 1_048_576)],
        ));
    }

    public function testTheTwoFilesOfAnImportAreMadeFromWhatWaited(): void
    {
        $at    = new \DateTimeImmutable('2026-11-06 06:00:00');
        $files = RegistryImportFile::pair(self::waiting('h1', $at), ['file' => 'archive/2026-11-06/amm.xml', 'dictionary' => 'archive/2026-11-06/dict.xml'], 'Ligne de commande');

        self::assertSame(
            [['amm', 'amm.xml', 'h1', 2048, 'archive/2026-11-06/amm.xml'], ['dict', 'dict.xml', 'h1-dict', 512, 'archive/2026-11-06/dict.xml']],
            array_map(static fn (RegistryImportFile $f): array => [$f->kind, $f->name, $f->sha256, $f->size, $f->storedPath], $files),
        );
        self::assertSame([$at, 'Ligne de commande'], [$files[1]->receivedAt, $files[1]->importedBy]);
    }

    /**
     * @param list<RegistryImportView> $imports
     */
    private function repository(string $source, int $limit, array $imports, int $authorizations): RegistryImportReadRepositoryInterface
    {
        $repository = $this->createMock(RegistryImportReadRepositoryInterface::class);
        $repository->expects(self::once())->method('imports')->with($source, $limit)->willReturn($imports);
        $repository->expects(self::once())->method('authorizationCount')->willReturn($authorizations);

        return $repository;
    }

    /**
     * @return array{file: string, dictionary: string, modifiedAt: \DateTimeImmutable, fileHash: string, fileSize: int, dictionaryHash: string, dictionarySize: int}
     */
    private static function waiting(string $hash, \DateTimeImmutable $at): array
    {
        return ['file' => '/x/amm.xml', 'dictionary' => '/x/dict.xml', 'modifiedAt' => $at, 'fileHash' => $hash, 'fileSize' => 2048, 'dictionaryHash' => $hash . '-dict', 'dictionarySize' => 512];
    }

    private static function file(string $hash, ?string $by): RegistryImportFile
    {
        return new RegistryImportFile('amm', 'amm.xml', $hash, 2048, 'archive/2026-10-30/amm.xml', new \DateTimeImmutable('2026-10-30 06:00:00'), $by);
    }

    private static function import(string $id, string $status): RegistryImportView
    {
        return new RegistryImportView($id, $status, new \DateTimeImmutable('2026-11-06 06:00:00'), null, null, 0, 0, 0);
    }
}
