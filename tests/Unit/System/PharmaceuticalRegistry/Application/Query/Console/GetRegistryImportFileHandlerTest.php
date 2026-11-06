<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\PharmaceuticalRegistry\Application\Query\Console;

use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportFilesInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImportFile;
use App\System\PharmaceuticalRegistry\Application\Query\Console\GetRegistryImportFileHandler;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportFile;
use App\Tests\Unit\System\PharmaceuticalRegistry\Support\InMemoryRegistryImportFileLog;
use PHPUnit\Framework\TestCase;

final class GetRegistryImportFileHandlerTest extends TestCase
{
    private const string IMPORT = '01960000-0000-7000-8000-0000000fa001';

    public function testAKeptFileIsFoundWithTheNameToGiveIt(): void
    {
        $files = $this->createMock(RegistryImportFilesInterface::class);
        $files->expects(self::once())->method('kept')->with('ANMV', 'archive/2026-11-06/dict.xml')->willReturn('/storage/archive/2026-11-06/dict.xml');

        self::assertSame(
            ['path' => '/storage/archive/2026-11-06/dict.xml', 'name' => 'ANMV-2026-11-06-dict.xml', 'sha256' => 'h-dict'],
            new GetRegistryImportFileHandler($this->log(), $files)(new GetRegistryImportFile(self::IMPORT, 'dict')),
        );
    }

    public function testNothingWhenTheImportTheFileOrItsCopyIsGone(): void
    {
        $files = self::createStub(RegistryImportFilesInterface::class);
        $files->method('kept')->willReturn(null);
        $handler = new GetRegistryImportFileHandler($this->log(), $files);

        self::assertNull($handler(new GetRegistryImportFile(self::IMPORT, 'amm')), 'the copy is gone');
        self::assertNull($handler(new GetRegistryImportFile('01960000-0000-7000-8000-0000000fa002', 'amm')), 'no such import');
        self::assertNull(new GetRegistryImportFileHandler(new InMemoryRegistryImportFileLog(), $files)(new GetRegistryImportFile(self::IMPORT, 'other')), 'no such file');
    }

    private function log(): InMemoryRegistryImportFileLog
    {
        $at  = new \DateTimeImmutable('2026-11-06 06:00:00');
        $log = new InMemoryRegistryImportFileLog();
        $log->record(self::IMPORT, 'ANMV', [
            new RegistryImportFile('amm', 'amm.xml', 'h', 2048, 'archive/2026-11-06/amm.xml', $at, 'Djamil Soidiki'),
            new RegistryImportFile('dict', 'dict.xml', 'h-dict', 512, 'archive/2026-11-06/dict.xml', $at, 'Djamil Soidiki'),
        ]);

        return $log;
    }
}
