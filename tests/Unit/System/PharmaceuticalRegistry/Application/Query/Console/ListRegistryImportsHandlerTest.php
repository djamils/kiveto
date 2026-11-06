<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImports;
use App\System\PharmaceuticalRegistry\Application\Query\Console\ListRegistryImportsHandler;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryStatusView;
use PHPUnit\Framework\TestCase;

final class ListRegistryImportsHandlerTest extends TestCase
{
    public function testTheRegistryShowsItsLatestImportsAndTheFilesWaiting(): void
    {
        $imports    = [self::import('a', 'RUNNING'), self::import('b', 'APPLIED'), self::import('c', 'APPLIED')];
        $modifiedAt = new \DateTimeImmutable('2026-11-06 06:00:00', new \DateTimeZone('UTC'));
        $files      = $this->createMock(RegistryImportFilesInterface::class);
        $files->expects(self::once())->method('current')->with('ANMV')->willReturn(['file' => '/x/amm.xml', 'dictionary' => '/x/dict.xml', 'modifiedAt' => $modifiedAt]);

        $status = new ListRegistryImportsHandler($this->repository('ANMV', 8, $imports, 4210), $files)(new ListRegistryImports());

        self::assertSame([4210, $imports, true, $modifiedAt], [$status->authorizations, $status->imports, $status->filesReady, $status->filesModifiedAt]);
        self::assertSame('a', $status->latest()?->id);
        self::assertSame('b', $status->inService()?->id, 'the latest import that went through');
    }

    public function testNoFileWaitingAndAtLeastOneImportAsked(): void
    {
        $files = $this->createMock(RegistryImportFilesInterface::class);
        $files->expects(self::once())->method('current')->with('EMA_UPD')->willReturn(null);

        $status = new ListRegistryImportsHandler($this->repository('EMA_UPD', 1, [], 0), $files)(new ListRegistryImports(0, 'EMA_UPD'));

        self::assertSame([false, null, null, null], [$status->filesReady, $status->filesModifiedAt, $status->latest(), $status->inService()]);
    }

    public function testNoImportWentThroughYet(): void
    {
        $status = new RegistryStatusView(0, [self::import('a', 'FAILED'), self::import('b', 'RUNNING')], false, null);

        self::assertSame('a', $status->latest()?->id);
        self::assertNull($status->inService());
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

    private static function import(string $id, string $status): RegistryImportView
    {
        return new RegistryImportView($id, $status, new \DateTimeImmutable('2026-11-06 06:00:00'), null, null, 0, 0, 0);
    }
}
