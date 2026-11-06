<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImport;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImportHandler;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryDiffLine;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportDetail;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use App\Tests\Unit\System\PharmaceuticalRegistry\Support\InMemoryRegistryImportFileLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GetRegistryImportHandlerTest extends TestCase
{
    private const string ID = '01960000-0000-7000-8000-0000000a2001';

    public function testAPageOfTheChangesOfOneKindIsRead(): void
    {
        $import     = self::import();
        $line       = new RegistryDiffLine('FR/V/0000001 1/2010', 'UPDATE', 'Méloxicam', 'Vetoquinol', ['holderLaboratoryLabel']);
        $repository = $this->createMock(RegistryImportReadRepositoryInterface::class);
        $repository->expects(self::once())->method('import')->with(self::ID)->willReturn($import);
        $repository->expects(self::once())->method('countDiff')->with(self::ID, 'UPDATE')->willReturn(25);
        $repository->expects(self::once())->method('diff')->with(self::ID, 'UPDATE', 10, 10)->willReturn([$line]);

        $detail = new GetRegistryImportHandler($repository, new InMemoryRegistryImportFileLog())(new GetRegistryImport(self::ID, 'UPDATE', 2));

        self::assertInstanceOf(RegistryImportDetail::class, $detail);
        self::assertSame([$import->id, $import->status, []], [$detail->import->id, $detail->import->status, $detail->import->files], 'no file kept for it');
        self::assertSame([[$line], 25, 2, 10, 3], [$detail->lines, $detail->total, $detail->page, $detail->perPage, $detail->pageCount()]);
    }

    public function testTheImportComesWithItsSourceFiles(): void
    {
        $repository = self::createStub(RegistryImportReadRepositoryInterface::class);
        $repository->method('import')->willReturn(self::import());
        $repository->method('countDiff')->willReturn(0);
        $log  = new InMemoryRegistryImportFileLog();
        $file = new RegistryImportFile('amm', 'amm.xml', 'h', 6, 'archive/x/amm.xml', new \DateTimeImmutable(), 'Djamil Soidiki');
        $log->record(self::ID, 'ANMV', [$file]);

        $detail = new GetRegistryImportHandler($repository, $log)(new GetRegistryImport(self::ID));

        self::assertNotNull($detail);
        self::assertSame([$file], $detail->import->files);
        self::assertSame('Djamil Soidiki', $detail->import->importedBy());
    }

    #[DataProvider('provideTheKindAndThePageAreKeptWithinBoundsCases')]
    public function testTheKindAndThePageAreKeptWithinBounds(?string $kind, ?string $readKind, int $page, int $perPage, int $keptPage, int $offset, int $limit): void
    {
        $repository = $this->createMock(RegistryImportReadRepositoryInterface::class);
        $repository->method('import')->willReturn(self::import());
        $repository->expects(self::once())->method('countDiff')->with(self::ID, $readKind)->willReturn(25);
        $repository->expects(self::once())->method('diff')->with(self::ID, $readKind, $offset, $limit)->willReturn([]);

        self::assertSame($keptPage, new GetRegistryImportHandler($repository, new InMemoryRegistryImportFileLog())(new GetRegistryImport(self::ID, $kind, $page, $perPage))?->page);
    }

    /**
     * @return iterable<string, array{?string, ?string, int, int, int, int, int}>
     */
    public static function provideTheKindAndThePageAreKeptWithinBoundsCases(): iterable
    {
        yield 'every kind' => [null, null, 1, 10, 1, 0, 10];

        yield 'an unknown kind reads every kind' => ['DELETE', null, 1, 10, 1, 0, 10];

        yield 'withdrawn ones' => ['WITHDRAW', 'WITHDRAW', 1, 10, 1, 0, 10];

        yield 'added ones' => ['CREATE', 'CREATE', 1, 10, 1, 0, 10];

        yield 'past the last page' => [null, null, 9, 10, 3, 20, 10];

        yield 'before the first page' => [null, null, -2, 10, 1, 0, 10];

        yield 'at least one per page' => [null, null, 4, 0, 4, 3, 1];
    }

    public function testAnImportWithoutChangesReadsNoPage(): void
    {
        $repository = $this->createMock(RegistryImportReadRepositoryInterface::class);
        $repository->method('import')->willReturn(self::import());
        $repository->expects(self::once())->method('countDiff')->willReturn(0);
        $repository->expects(self::never())->method('diff');

        $detail = new GetRegistryImportHandler($repository, new InMemoryRegistryImportFileLog())(new GetRegistryImport(self::ID, page: 3));

        self::assertNotNull($detail);
        self::assertSame([[], 0, 1, 1], [$detail->lines, $detail->total, $detail->page, $detail->pageCount()]);
    }

    public function testAnUnknownImportIsNull(): void
    {
        $repository = $this->createMock(RegistryImportReadRepositoryInterface::class);
        $repository->expects(self::once())->method('import')->willReturn(null);
        $repository->expects(self::never())->method('countDiff');

        self::assertNull(new GetRegistryImportHandler($repository, new InMemoryRegistryImportFileLog())(new GetRegistryImport(self::ID)));
    }

    private static function import(): RegistryImportView
    {
        return new RegistryImportView(self::ID, 'APPLIED', new \DateTimeImmutable('2026-11-06 06:00:00'), new \DateTimeImmutable('2026-11-06 06:00:48'), null, 12, 37, 4);
    }
}
